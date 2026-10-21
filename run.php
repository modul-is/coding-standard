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

// e-mails and PDF/print templates are never rendered by a browser, so no CSP applies to them
// and a class from an external stylesheet would not reach them - inline styles are correct there.
// The globs match a directory named Mail/Print at any depth (Foo/Mail, FooModule/templates/Print),
// so a whole MailModule/PrintModule with regular browser rendered templates is not skipped
$inlineExclude = '*/Mail/*,*/Print/*';

for ($i = 1; $i < $argc; $i++) {
	$arg = $argv[$i];
	if ($arg === '--preset' && isset($argv[$i + 1])) {
		$preset = $argv[++$i];
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

$inlineStylesOk = checkInlineStyles($paths, !$dryRun, $inlineExclude);

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
// <style> element), so every one of them is reported. No generic classes are generated - with --fix
// a style is replaced only when all its declarations have an exact Bootstrap 5/core utility class
// (width: 100% -> w-100, margin-top: 1rem -> mt-3, cursor: pointer -> cursor-pointer). Everything else
// is left for a manual fix - a utility class, the hidden attribute or a named class of the element.
// The <style nonce="..."> element is left untouched.
function checkInlineStyles(array $paths, bool $fix, string $exclude = ''): bool
{
	// a whole HTML tag, matched against the masked content where no expression can contain ">"
	$tagPattern = '~<[a-zA-Z][a-zA-Z0-9:_.-]*(?:"[^"]*"|\'[^\']*\'|[^>"\'])*/?>~';

	// a style="..." or style='...' attribute (not the <style> element - it has no whitespace before
	// "style"). The whitespace is a separate group, it may be a masked Latte tag that has to survive
	$stylePattern = '~\s+(style\s*=\s*("[^"]*"|\'[^\']*\'))~i';

	// a Latte/Twig control structure between the attributes of the tag - it splits the tag into
	// parts that are rendered independently. Inside an attribute value it only makes the value dynamic
	$controlPattern = '~\{(?:/|if\b|ifset\b|ifchanged\b|else\b|elseif\b|elseifset\b|foreach\b|for\b|while\b|first\b|last\b|sep\b|switch\b|case\b|iterateWhile\b|try\b)|\{%~i';

	$replaced = [];
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
				$searchFrom = $attribute[1][1] + strlen($attribute[1][0]);
				$declarations = parseInlineStyle($value);

				if($declarations === null)
				{
					$manual[$path][$value] = 'dynamic value - move it into a class, or into a CSS variable set by a script';

					continue;
				}

				[$classes, $conflicts, $hints] = mapInlineStyle($declarations);

				if($hints)
				{
					$manual[$path][$value] = implode('; ', $hints);

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

				if($classes)
				{
					// a utility class of the same group already on the element (class="mt-2" style="margin-top: 0")
					// would compete with the new one - both are !important, so the result would depend on their order
					if(hasConflictingClass($strippedTag, $conflicts))
					{
						$manual[$path][$value] = 'the element already has a conflicting class, use "' . implode(' ', $classes) . '" instead of it';

						continue;
					}

					$updatedTag = addClassToTag($strippedTag, maskExpressions($strippedTag), implode(' ', $classes), $start, $end);

					if($updatedTag === null)
					{
						$manual[$path][$value] = 'no safe place for the class, use "' . implode(' ', $classes) . '"';

						continue;
					}

					$strippedTag = $updatedTag;
				}

				$tag = $strippedTag;
				$maskedTag = maskExpressions($tag);
				$searchFrom = $removeFrom;

				$replaced[$path][$value] = $classes ? implode(' ', $classes) : '(empty, removed)';
			}

			$content .= $tag;
		}

		$content .= substr($original, $offset);

		if($fix && $content !== $original)
		{
			file_put_contents($path, $content);
		}
	}

	if(!$replaced && !$manual)
	{
		return true;
	}

	print PHP_EOL;

	if($replaced)
	{
		print ($fix ? 'Inline styles replaced by utility classes:' : 'Inline styles that can be replaced by utility classes (--fix):') . PHP_EOL;
		printInlineStyles($replaced);
	}

	if($manual)
	{
		print 'Inline styles to fix manually (CSP blocks them):' . PHP_EOL;
		printInlineStyles($manual);
	}

	return $fix && !$manual;
}


function printInlineStyles(array $styles): void
{
	foreach($styles as $path => $values)
	{
		print "\t" . $path . PHP_EOL;

		foreach($values as $value => $note)
		{
			print "\t\tstyle=\"" . $value . '" -> ' . $note . PHP_EOL;
		}
	}

	print PHP_EOL;
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


// Maps inline style declarations to Bootstrap 5 and core utility classes, only an exact match is used.
// Returns [classes, conflict patterns, hints] - the hints are filled when any declaration
// has no exact class and the style has to be fixed manually
function mapInlineStyle(array $declarations): array
{
	$positions = ['0' => '0', '50%' => '50', '100%' => '100'];

	$keywords = [
		'width' => ['w-|vw-', ['25%' => 'w-25', '50%' => 'w-50', '75%' => 'w-75', '100%' => 'w-100', 'auto' => 'w-auto', '100vw' => 'vw-100']],
		'height' => ['h-|vh-', ['25%' => 'h-25', '50%' => 'h-50', '75%' => 'h-75', '100%' => 'h-100', 'auto' => 'h-auto', '100vh' => 'vh-100']],
		'max-width' => ['mw-', ['100%' => 'mw-100']],
		'max-height' => ['mh-', ['100%' => 'mh-100']],
		'min-width' => ['min-vw-', ['100vw' => 'min-vw-100']],
		'min-height' => ['min-vh-', ['100vh' => 'min-vh-100']],
		'text-align' => ['text-(?:start|center|end)', ['left' => 'text-start', 'start' => 'text-start', 'center' => 'text-center', 'right' => 'text-end', 'end' => 'text-end']],
		'white-space' => ['text-(?:nowrap|wrap)', ['nowrap' => 'text-nowrap', 'normal' => 'text-wrap']],
		'word-wrap' => ['text-break', ['break-word' => 'text-break']],
		'overflow-wrap' => ['text-break', ['break-word' => 'text-break']],
		'text-transform' => ['text-(?:lowercase|uppercase|capitalize)', ['lowercase' => 'text-lowercase', 'uppercase' => 'text-uppercase', 'capitalize' => 'text-capitalize']],
		'text-decoration' => ['text-decoration-', ['none' => 'text-decoration-none', 'underline' => 'text-decoration-underline', 'line-through' => 'text-decoration-line-through']],
		'font-weight' => ['fw-', ['bold' => 'fw-bold', '700' => 'fw-bold', 'bolder' => 'fw-bolder', '600' => 'fw-semibold', '500' => 'fw-medium', 'normal' => 'fw-normal', '400' => 'fw-normal', '300' => 'fw-light', 'lighter' => 'fw-lighter']],
		'font-style' => ['fst-', ['italic' => 'fst-italic', 'normal' => 'fst-normal']],
		'line-height' => ['lh-', ['1' => 'lh-1', '1.25' => 'lh-sm', '1.5' => 'lh-base', '2' => 'lh-lg']],
		'vertical-align' => ['align-(?:baseline|top|middle|bottom|text-)', ['baseline' => 'align-baseline', 'top' => 'align-top', 'middle' => 'align-middle', 'bottom' => 'align-bottom', 'text-top' => 'align-text-top', 'text-bottom' => 'align-text-bottom']],
		'float' => ['float-', ['left' => 'float-start', 'right' => 'float-end', 'none' => 'float-none']],
		'position' => ['position-', ['static' => 'position-static', 'relative' => 'position-relative', 'absolute' => 'position-absolute', 'fixed' => 'position-fixed', 'sticky' => 'position-sticky']],
		'top' => ['top-', array_map(fn(string $value): string => 'top-' . $value, $positions)],
		'bottom' => ['bottom-', array_map(fn(string $value): string => 'bottom-' . $value, $positions)],
		'left' => ['start-', array_map(fn(string $value): string => 'start-' . $value, $positions)],
		'right' => ['end-', array_map(fn(string $value): string => 'end-' . $value, $positions)],
		'overflow' => ['overflow-', ['auto' => 'overflow-auto', 'hidden' => 'overflow-hidden', 'visible' => 'overflow-visible', 'scroll' => 'overflow-scroll']],
		'visibility' => ['(?:in)?visible', ['visible' => 'visible', 'hidden' => 'invisible']],
		'opacity' => ['opacity-', ['0' => 'opacity-0', '.25' => 'opacity-25', '0.25' => 'opacity-25', '.5' => 'opacity-50', '0.5' => 'opacity-50', '.75' => 'opacity-75', '0.75' => 'opacity-75', '1' => 'opacity-100']],
		'pointer-events' => ['pe-(?:none|auto)', ['none' => 'pe-none', 'auto' => 'pe-auto']],
		'user-select' => ['user-select-', ['all' => 'user-select-all', 'auto' => 'user-select-auto', 'none' => 'user-select-none']],
		'object-fit' => ['object-fit-', ['contain' => 'object-fit-contain', 'cover' => 'object-fit-cover', 'fill' => 'object-fit-fill', 'scale-down' => 'object-fit-scale', 'none' => 'object-fit-none']],
		'flex-direction' => ['flex-(?:row|column)', ['row' => 'flex-row', 'row-reverse' => 'flex-row-reverse', 'column' => 'flex-column', 'column-reverse' => 'flex-column-reverse']],
		'flex-wrap' => ['flex-(?:wrap|nowrap)', ['wrap' => 'flex-wrap', 'nowrap' => 'flex-nowrap', 'wrap-reverse' => 'flex-wrap-reverse']],
		'justify-content' => ['justify-content-', ['flex-start' => 'justify-content-start', 'start' => 'justify-content-start', 'flex-end' => 'justify-content-end', 'end' => 'justify-content-end', 'center' => 'justify-content-center', 'space-between' => 'justify-content-between', 'space-around' => 'justify-content-around', 'space-evenly' => 'justify-content-evenly']],
		'align-items' => ['align-items-', ['flex-start' => 'align-items-start', 'start' => 'align-items-start', 'flex-end' => 'align-items-end', 'end' => 'align-items-end', 'center' => 'align-items-center', 'baseline' => 'align-items-baseline', 'stretch' => 'align-items-stretch']],
		'cursor' => ['cursor-', ['pointer' => 'cursor-pointer']], // core - global.css of the skeleton
	];

	$spacings = [
		'margin' => 'm',
		'margin-top' => 'mt',
		'margin-right' => 'me',
		'margin-bottom' => 'mb',
		'margin-left' => 'ms',
		'padding' => 'p',
		'padding-top' => 'pt',
		'padding-right' => 'pe',
		'padding-bottom' => 'pb',
		'padding-left' => 'ps',
		'gap' => 'gap',
		'row-gap' => 'row-gap',
		'column-gap' => 'column-gap',
	];

	$classes = [];
	$conflicts = [];
	$notes = [];
	$manual = false;

	foreach($declarations as $declaration)
	{
		[$property, $value] = explode(': ', $declaration, 2);
		$value = strtolower(trim(preg_replace('~\s*!important$~i', '', $value)));
		$found = null;

		if(isset($keywords[$property][1][$value]))
		{
			$found = [$keywords[$property][1][$value]];
			$conflicts[] = $keywords[$property][0];
		}
		elseif(isset($spacings[$property]))
		{
			$found = mapSpacing($spacings[$property], $value);

			// any spacer class of the same kind - pe-none/pe-auto are pointer events, not a padding
			$conflicts[] = match($property[0]) {
				'm' => 'm[tbxyse]?-',
				'p' => 'p[tbxyse]?-(?!none|auto)',
				default => '(?:row-|column-)?gap-',
			};
		}

		if($found !== null)
		{
			array_push($classes, ...$found);
			$notes[] = $declaration . ' => ' . implode(' ', $found);

			continue;
		}

		$manual = true;
		$notes[] = $declaration . ' => ' . inlineStyleHint($property, $value);
	}

	return $manual ? [[], [], $notes] : [array_values(array_unique($classes)), $conflicts, []];
}


// Maps a margin/padding/gap value to spacer classes (m-3, mt-0, my-2 mx-auto), returns null when there is none
function mapSpacing(string $prefix, string $value): ?array
{
	$shorthand = $prefix === 'm' || $prefix === 'p';
	$sides = array_map(fn(string $side): ?string => spacerIndex($side, $prefix[0] === 'm'), preg_split('~\s+~', $value));

	if(in_array(null, $sides, true) || count($sides) > ($shorthand ? 4 : 1))
	{
		return null;
	}

	if(!$shorthand)
	{
		return [$prefix . '-' . $sides[0]];
	}

	// top, right, bottom, left as in the CSS shorthand
	[$top, $right, $bottom, $left] = [$sides[0], $sides[1] ?? $sides[0], $sides[2] ?? $sides[0], $sides[3] ?? $sides[1] ?? $sides[0]];

	if($top === $right && $top === $bottom && $top === $left)
	{
		return [$prefix . '-' . $top];
	}

	return [
		...($top === $bottom ? [$prefix . 'y-' . $top] : [$prefix . 't-' . $top, $prefix . 'b-' . $bottom]),
		...($right === $left ? [$prefix . 'x-' . $right] : [$prefix . 's-' . $left, $prefix . 'e-' . $right]),
	];
}


// Returns the Bootstrap spacer of a length (0, .25rem, .5rem, 1rem, 1.5rem, 3rem or the same in px)
function spacerIndex(string $value, bool $allowAuto): ?string
{
	if($allowAuto && $value === 'auto')
	{
		return 'auto';
	}

	if(!preg_match('~^(\d*\.?\d+)(px|rem)?$~', $value, $match) || ($match[2] ?? '') === '' && (float) $match[1] !== 0.0)
	{
		return null;
	}

	$px = (float) $match[1] * (($match[2] ?? '') === 'rem' ? 16 : 1);
	$index = array_search($px, [0.0, 4.0, 8.0, 16.0, 24.0, 48.0], true);

	return $index === false ? null : (string) $index;
}


// What to use instead of a declaration that has no exact utility class
function inlineStyleHint(string $property, string $value): string
{
	return match(true) {
		$property === 'display' && $value === 'none' => 'hidden attribute (toggle it in JS by .prop(\'hidden\', ...) instead of .show()/.hide())',
		// d-* is !important - jQuery .show()/.hide() cannot override it, so it is never set automatically
		$property === 'display' => 'd-' . $value . ' when no script shows/hides the element, otherwise a named class',
		str_starts_with($property, 'margin') || str_starts_with($property, 'padding') || str_ends_with($property, 'gap') => 'a spacer class - spacers are 0, .25rem, .5rem, 1rem, 1.5rem, 3rem (mt-0 ... mt-5)',
		default => 'a named class of the element in the project stylesheet',
	};
}


// Whether the class or n:class attributes of the tag hold a class matching one of the patterns
function hasConflictingClass(string $tag, array $patterns): bool
{
	if(!$patterns)
	{
		return false;
	}

	preg_match_all('~(?:\s|^)(?:n:)?class\s*=\s*(["\'])(.*?)\1~i', maskExpressions($tag), $matches, PREG_OFFSET_CAPTURE);

	foreach($matches[2] as [$value, $position])
	{
		if(preg_match('~(?<![\w-])(?:' . implode('|', $patterns) . ')~', substr($tag, $position, strlen($value))))
		{
			return true;
		}
	}

	return false;
}
