<?php

declare(strict_types=1);

// Autoloader
if (
	!(is_file($file = ($vendorDir = __DIR__ . '/vendor') . '/autoload.php') && include $file) &&
	!(is_file($file = ($vendorDir = __DIR__ . '/../..') . '/autoload.php') && include $file)
) {
	fwrite(STDERR, "Install packages using Composer.\n");
	exit(1);
}


// Argument Parsing
$paths = [];
$preset = null;
$dryRun = true;
$inlineCssFile = 'www/assets/css/core/project.css';
$inlineClassPrefix = 'is-';

// e-mails and PDF/print templates are never rendered by a browser, so no CSP applies to them
// and an external stylesheet would not reach them - inline styles are correct there.
// The globs match a directory named Mail/Print at any depth (Foo/Mail, FooModule/templates/Print),
// so a whole MailModule/PrintModule with regular browser rendered templates is not skipped
$inlineExclude = '*/Mail/*,*/Print/*';

for ($i = 1; $i < $argc; $i++) {
	$arg = $argv[$i];
	if ($arg === '--preset' && isset($argv[$i + 1])) {
		$preset = $argv[++$i];
	} elseif ($arg === '--inline-css' && isset($argv[$i + 1])) {
		$inlineCssFile = $argv[++$i];
	} elseif ($arg === '--inline-class-prefix' && isset($argv[$i + 1])) {
		$inlineClassPrefix = $argv[++$i];
	} elseif ($arg === '--inline-exclude' && isset($argv[$i + 1])) {
		$inlineExclude = $argv[++$i];
	} elseif ($arg === '--fix' || $arg === 'fix') {
		$dryRun = false;
	} elseif ($arg === 'check') {
		$dryRun = true;
	} elseif ($arg === '--help' || $arg === '-h') {
		echo "Usage: php fix.php [check|fix] [--preset <name>] [path1 path2 ...]\n";
		echo "  check (default): Run tools in dry-run mode.\n";
		echo "  fix: Run tools and apply fixes.\n";
		echo "  --preset <name>: Specify preset (e.g., php81). Autodetected if omitted.\n";
		echo "  --inline-css <path>: Stylesheet the extracted inline styles are appended to. Default: www/assets/css/core/project.css\n";
		echo "  --inline-class-prefix <prefix>: Prefix of the generated CSS classes. Default: is-\n";
		echo "  --inline-exclude <globs>: Comma separated templates left untouched. Default: */Mail/*,*/Print/*\n";
		echo "  path1 path2 ...: Specific files or directories to process. Defaults to src/, tests/ or ./\n";
		exit(0);
	} elseif (!str_starts_with($arg, '-')) {
		$paths[] = $arg;
	} else {
		fwrite(STDERR, "Warning: Ignoring unknown option '{$arg}'\n");
	}
}


// Determine Project Root (essential for finding composer.json and relative paths)
$root = getcwd(); // Start from the current working directory
while (!is_file("$root/composer.json") && substr_count($root, DIRECTORY_SEPARATOR) > 1) {
	$root = dirname($root);
}
if (!is_file("$root/composer.json")) {
	$root = getcwd();
	echo "Warning: Could not find composer.json, using current directory '{$root}' as project root.\n";
}



// Instantiate and Configure Checker
$checker = new Checker($vendorDir, $root, $dryRun, $preset);
echo 'Mode: ' . ($dryRun ? 'Check (dry-run)' : 'Fix') . "\n";

// Determine and set paths
$paths = $paths ?: array_filter(['src', 'tests'], 'is_dir') ?: ['.'];
$checker->setPaths($paths);
echo 'Paths: ' . implode(', ', $paths) . "\n";
if ($preset) {
	echo "Preset: {$preset}\n";
}

// Signal Handling
if (function_exists('pcntl_signal')) {
	pcntl_signal(SIGINT, function () use ($checker) {
		pcntl_signal(SIGINT, SIG_DFL);
		throw new Exception;
	});
} elseif (function_exists('sapi_windows_set_ctrl_handler')) {
	sapi_windows_set_ctrl_handler(function () use ($checker) {
		throw new Exception;
	});
}

// Run
try {
	$fixerOk = $checker->runFixer();
	echo "\n\n";
	$snifferOk = $checker->runSniffer();
} catch (Exception) {
	echo "Terminated\n";
	$checker->cleanup();
	exit(1);
}

$checker->cleanup();

fixSpaces($argv);

$inlineStylesOk = checkInlineStyles($paths, $root, !$dryRun, $inlineCssFile, $inlineClassPrefix, $inlineExclude);

if ($fixerOk && $snifferOk && $inlineStylesOk) {
	echo $dryRun ? "Code style checks passed.\n" : "Code style fixed successfully.\n";
	exit(0);
} else {
	echo $dryRun ? "Code style issues found.\n" : "Code style fixing failed or issues remain.\n";
	exit(1);
}


// Space fixer (Latte, Twig, Neon)
function fixSpaces(array $arguments)
{
	$files = '';
	$count = 0;

	$finder = new \Symfony\Component\Finder\Finder;
	$finder->files()->name(['*.latte', '*.twig', '*.neon'])->in($arguments[2]);

	foreach($finder as $file)
	{
		$path = $file->getRealPath();

		$content = file_get_contents($path);

		if(strpos($content, '    '))
		{
			if(in_array('--fix', $arguments, true))
			{
				$content = str_replace('    ', "\t", $content);

				file_put_contents($path, $content);
			}

			$files .= $path . PHP_EOL;
			$count++;
		}
	}

	if($count != 0)
	{
		print PHP_EOL;

		if(in_array('--fix', $arguments, true))
		{
			print "Files converted to tabs:" . PHP_EOL;
		}
		else
		{
			print "Files can be converted to tabs:" . PHP_EOL;
		}

		print $files . PHP_EOL;
	}
}


// Inline styles checker/fixer (Latte, Twig)
// A style="..." attribute cannot be whitelisted by a CSP nonce (a nonce only works on the
// <style> element), so the declarations are moved into a generated stylesheet and the element
// gets a hash based CSS class instead. The <style nonce="..."> element is left untouched.
// Styles containing a Latte/Twig expression cannot be turned into a static rule and are reported
// for a manual fix instead of being thrown away. The same applies to a style attribute wrapped in
// a Latte/Twig condition - the class would end up on the element unconditionally.
function checkInlineStyles(array $paths, string $root, bool $fix, string $cssFile, string $classPrefix, string $exclude = ''): bool
{
	// a whole HTML tag, matched against the masked content where no expression can contain ">"
	$tagPattern = '~<[a-zA-Z][a-zA-Z0-9:_.-]*(?:"[^"]*"|\'[^\']*\'|[^>"\'])*/?>~';

	// a style="..." or style='...' attribute (not the <style> element - it has no whitespace before
	// "style"). The whitespace is a separate group, it may be a masked Latte tag that has to survive
	$stylePattern = '~\s+(style\s*=\s*("[^"]*"|\'[^\']*\'))~i';

	// a Latte/Twig control structure between the attributes of the tag - it splits the tag into
	// parts that are rendered independently. Inside an attribute value it only makes the value dynamic
	$controlPattern = '~\{(?:/|if\b|ifset\b|ifchanged\b|else\b|elseif\b|elseifset\b|foreach\b|for\b|while\b|first\b|last\b|sep\b|switch\b|case\b|iterateWhile\b|try\b)|\{%~i';

	$rules = [];
	$extracted = [];
	$manual = [];

	foreach(findTemplates($paths, $exclude) as $path)
	{
		$original = file_get_contents($path);
		$masked = maskExpressions($original);

		preg_match_all($tagPattern, $masked, $matches, PREG_OFFSET_CAPTURE);

		$content = '';
		$offset = 0;

		foreach($matches[0] as [$maskedTag, $position])
		{
			$tag = substr($original, $position, strlen($maskedTag));

			$content .= substr($original, $offset, $position - $offset);
			$offset = $position + strlen($maskedTag);

			// a conditionally rendered tag may hold more than one style attribute, one per branch
			$searchFrom = 0;

			while(preg_match($stylePattern, $maskedTag, $attribute, PREG_OFFSET_CAPTURE, $searchFrom))
			{
				$value = trim(substr($tag, $attribute[2][1] + 1, strlen($attribute[2][0]) - 2));
				$declarations = parseInlineStyle($value);
				$searchFrom = $attribute[1][1] + strlen($attribute[1][0]);

				if($declarations === null)
				{
					$manual[$path][] = $value;

					continue;
				}

				// the part of the tag the attribute is rendered in - the class has to end up in the same one
				[$start, $end] = findTagPart(blankAttributeValues($tag, $maskedTag), $attribute[1][1], $controlPattern);

				// the whitespace in front of the attribute is dropped only when it really is whitespace,
				// not when it is a masked Latte tag that has to stay in place
				$removeFrom = $attribute[0][1] + strlen(rtrim(substr($tag, $attribute[0][1], $attribute[1][1] - $attribute[0][1])));
				$removeLength = $attribute[1][1] + strlen($attribute[1][0]) - $removeFrom;

				$strippedTag = substr_replace($tag, '', $removeFrom, $removeLength);
				$end -= $removeLength;

				if($declarations !== [])
				{
					$class = $classPrefix . substr(md5(implode('; ', $declarations)), 0, 6);
					$updatedTag = addClassToTag($strippedTag, maskExpressions($strippedTag), $class, $start, $end);

					if($updatedTag === null)
					{
						$manual[$path][] = $value;

						continue;
					}

					$rules[$class] = $declarations;
					$strippedTag = $updatedTag;
				}

				$tag = $strippedTag;
				$maskedTag = maskExpressions($tag);
				$searchFrom = $removeFrom;

				$extracted[$path] = ($extracted[$path] ?? 0) + 1;
			}

			$content .= $tag;
		}

		$content .= substr($original, $offset);

		if($fix && $content !== $original)
		{
			file_put_contents($path, $content);
		}
	}

	if(!$extracted && !$manual)
	{
		return true;
	}

	$cssPath = preg_match('~^([a-zA-Z]:[\\\\/]|/)~', $cssFile) ? $cssFile : $root . '/' . $cssFile;

	print PHP_EOL;

	if($extracted)
	{
		print $fix ? 'Inline styles extracted into ' . $cssPath . ':' . PHP_EOL : 'Inline styles found in files:' . PHP_EOL;

		foreach($extracted as $path => $count)
		{
			print "\t" . $path . ' (' . $count . ')' . PHP_EOL;
		}

		if($fix)
		{
			writeInlineStyleSheet($cssPath, $rules);

			print PHP_EOL . 'Make sure the stylesheet is linked in the layout.' . PHP_EOL;
		}

		print PHP_EOL;
	}

	if($manual)
	{
		print 'Inline styles with a dynamic or conditional value - fix these manually:' . PHP_EOL;

		foreach($manual as $path => $values)
		{
			print "\t" . $path . PHP_EOL;

			foreach(array_unique($values) as $value)
			{
				print "\t\tstyle=\"" . $value . '"' . PHP_EOL;
			}
		}

		print PHP_EOL;
	}

	return $fix && !$manual;
}


// Blanks out the quoted attribute values of a tag, so that what is left is only what stands
// between the attributes - a Latte tag found there wraps whole attributes, one found in a value does not
function blankAttributeValues(string $tag, string $maskedTag): string
{
	preg_match_all('~"[^"]*"|\'[^\']*\'~', $maskedTag, $matches, PREG_OFFSET_CAPTURE);

	foreach($matches[0] as [$match, $position])
	{
		$tag = substr_replace($tag, str_repeat(' ', strlen($match)), $position, strlen($match));
	}

	return $tag;
}


// Blanks out Latte/Twig/PHP expressions, keeping the length of the content, so that the quotes,
// angle brackets and braces inside them cannot break the HTML tag matching -
// href="{plink ":$step->value:default", $hash}" used to end the tag at the "->" arrow
function maskExpressions(string $content): string
{
	$patterns = [
		'~\{\*.*?\*\}~s', // Latte comment
		'~\{\{.*?\}\}~s', // Twig expression
		'~\{%.*?%\}~s', // Twig statement
		'~\{\#.*?\#\}~s', // Twig comment
		'~<\?.*?(?:\?>|$)~s', // PHP
		'~\{[^{}]*\}~s', // Latte tag
	];

	foreach($patterns as $pattern)
	{
		$content = preg_replace_callback($pattern, fn(array $match): string => str_repeat(' ', strlen($match[0])), $content);
	}

	return $content;
}


// Returns the .latte and .twig files in the given paths, without the excluded ones
function findTemplates(array $paths, string $exclude = ''): array
{
	$directories = [];
	$files = [];

	foreach($paths as $path)
	{
		if(is_dir($path))
		{
			$directories[] = $path;
		}
		elseif(is_file($path) && preg_match('~\.(latte|twig)$~i', $path))
		{
			$files[] = (string) realpath($path);
		}
	}

	if($directories)
	{
		$finder = new \Symfony\Component\Finder\Finder;
		$finder->files()->name(['*.latte', '*.twig'])->in($directories);

		foreach($finder as $file)
		{
			$files[] = $file->getRealPath();
		}
	}

	$patterns = [];

	foreach(array_filter(array_map('trim', explode(',', $exclude))) as $glob)
	{
		$patterns[] = '~^' . str_replace(['\*', '\?'], ['.*', '.'], preg_quote($glob, '~')) . '$~i';
	}

	return array_filter(array_unique($files), function(string $file) use ($patterns): bool
	{
		$file = str_replace('\\', '/', $file);

		foreach($patterns as $pattern)
		{
			if(preg_match($pattern, $file))
			{
				return false;
			}
		}

		return true;
	});
}


// Splits an inline style value into normalized declarations,
// returns null when the value cannot be turned into a static CSS rule
function parseInlineStyle(string $value): ?array
{
	// Latte/Twig/PHP expression
	if(preg_match('~[{}]|<\?~', $value))
	{
		return null;
	}

	if(trim($value) === '')
	{
		return [];
	}

	$parts = [];
	$buffer = '';
	$depth = 0;
	$quote = null;

	foreach(str_split($value) as $char)
	{
		if($quote !== null)
		{
			$buffer .= $char;

			if($char === $quote)
			{
				$quote = null;
			}

			continue;
		}

		if($char === '"' || $char === "'")
		{
			$quote = $char;
		}
		elseif($char === '(')
		{
			$depth++;
		}
		elseif($char === ')')
		{
			$depth--;
		}
		elseif($char === ';' && $depth === 0)
		{
			$parts[] = $buffer;
			$buffer = '';

			continue;
		}

		$buffer .= $char;
	}

	$parts[] = $buffer;

	$declarations = [];

	foreach($parts as $part)
	{
		$part = trim($part);

		if($part === '')
		{
			continue;
		}

		if(!str_contains($part, ':'))
		{
			return null;
		}

		[$property, $declarationValue] = explode(':', $part, 2);

		$property = strtolower(trim($property));
		$declarationValue = trim(preg_replace('~\s+~', ' ', $declarationValue));

		if($property === '' || $declarationValue === '')
		{
			return null;
		}

		$declarations[] = $property . ': ' . $declarationValue;
	}

	return $declarations;
}


// Adds a CSS class to an HTML tag, merging it into the existing class or n:class attribute.
// The attributes are located in the masked tag, the value is taken from the real one at the same offset.
// Only the [start, end] part of the tag is used, so that a class of a conditionally rendered
// attribute stays in the same branch. Returns null when there is no safe place for the class
function addClassToTag(string $tag, string $maskedTag, string $class, int $start, int $end): ?string
{
	$part = substr($maskedTag, $start, $end - $start);
	$whole = $start === 0 && $end === strlen($maskedTag);

	// the part may begin right behind a Latte tag, so the attribute does not have to be preceded by whitespace
	if(preg_match('~(?:\s|^)class\s*=\s*(["\'])(.*?)\1~i', $part, $match, PREG_OFFSET_CAPTURE))
	{
		$position = $start + $match[2][1];
		$value = substr($tag, $position, strlen($match[2][0]));

		return substr_replace($tag, (trim($value) === '' ? '' : $value . ' ') . $class, $position, strlen($value));
	}

	// n:class takes a list of expressions, so the class has to be quoted
	if(preg_match('~(?:\s|^)n:class\s*=\s*(["\'])(.*?)\1~i', $part, $match, PREG_OFFSET_CAPTURE))
	{
		$position = $start + $match[2][1];
		$value = substr($tag, $position, strlen($match[2][0]));
		$literal = $match[1][0] === '"' ? "'" . $class . "'" : '"' . $class . '"';

		return substr_replace($tag, (trim($value) === '' ? '' : rtrim($value) . ', ') . $literal, $position, strlen($value));
	}

	if($whole)
	{
		return preg_replace('~^<[a-zA-Z][a-zA-Z0-9:_.-]*~', '$0 class="' . $class . '"', $tag, 1);
	}

	// a new class attribute can only be opened in the conditional part when the element has no other one,
	// two class attributes on one element would be invalid
	if(preg_match('~(?:\s|^)(?:n:)?class\s*=~i', $maskedTag))
	{
		return null;
	}

	// what the part is preceded by are the Latte tags opening it - the attribute in front of them
	// is rendered right next to the new one, so it has to be separated from it
	$before = substr($tag, 0, $start);

	while(preg_match('~\{[^{}]*\}$~', $before, $match))
	{
		$before = substr($before, 0, -strlen($match[0]));
	}

	$attribute = (preg_match('~(\s|^)$~', $before) ? '' : ' ')
		. 'class="' . $class . '"'
		. (preg_match('~^(\s|/?>|$)~', $part) ? '' : ' ');

	return substr_replace($tag, $attribute, $start, 0);
}


// Returns the boundaries of the part of the tag the given offset falls into. The parts are delimited
// by the Latte/Twig control structures standing between the attributes, each of them is rendered
// on its own - without them the whole tag is a single part
function findTagPart(string $outside, int $offset, string $controlPattern): array
{
	$start = 0;
	$end = strlen($outside);

	preg_match_all('~\{[^{}]*\}~s', $outside, $matches, PREG_OFFSET_CAPTURE);

	foreach($matches[0] as [$latteTag, $position])
	{
		if(!preg_match($controlPattern, $latteTag))
		{
			continue;
		}

		if($position < $offset)
		{
			$start = max($start, $position + strlen($latteTag));
		}
		else
		{
			$end = min($end, $position);
		}
	}

	return [$start, $end];
}


// Appends the extracted rules to the project stylesheet, keeping its current content untouched
function writeInlineStyleSheet(string $cssPath, array $rules): void
{
	$header = '/* Styles extracted from inline style attributes by modul-is/cs. */';
	$content = is_file($cssPath) ? file_get_contents($cssPath) : '';
	$appended = '';

	foreach($rules as $class => $declarations)
	{
		if(preg_match('~(^|[\s,}])\.' . preg_quote($class, '~') . '\s*\{~', $content))
		{
			continue;
		}

		$appended .= PHP_EOL . '.' . $class . ' {' . PHP_EOL . "\t" . implode(';' . PHP_EOL . "\t", $declarations) . ';' . PHP_EOL . '}' . PHP_EOL;
	}

	if($appended === '')
	{
		return;
	}

	if(!str_contains($content, $header))
	{
		$appended = PHP_EOL . $header . PHP_EOL . $appended;
	}

	$directory = dirname($cssPath);

	if(!is_dir($directory))
	{
		mkdir($directory, 0777, true);
	}

	file_put_contents($cssPath, $content === '' ? ltrim($appended, "\r\n") : rtrim($content, "\r\n") . PHP_EOL . $appended);
}
