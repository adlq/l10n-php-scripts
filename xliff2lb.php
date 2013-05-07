<?php
require_once("XliffFilter.php");

// Check that all required arguments were passed
if (count($argv) < 5)
{
	echo 'Syntax is "php xliff2lb.php input.xlf source-language output.xlf target-language"' . "\n";
	echo 'e.g. php xliff2lb.php nl-NL_raw.xliff en-GB nl-NL.xliff nl-NL csv >> buildXliffsOutput.csv' . "\n";
	exit("Missing parameters");
}

// Initialize XMLReader
$reader = new XMLReader();
$reader->open($argv[1], 'UTF-8');

// Initialize XMLWriter
$writer = new XMLWriter();
$writer->openMemory();
$writer->setIndent(true);

// Initialize XliffFilter
$xliffFilter = new XliffFilter();

// Initialize output file
$writer->startDocument('1.0', 'UTF-8');

$writer->startElement('xliff');
$writer->writeAttribute('xmlns', 'urn:oasis:names:tc:xliff:document:1.2');
$writer->writeAttribute('version', '1.2');

// Extract output file name from arguments
$outputFileName = $argv[3];

// Add <file> and <body> nodes to the output file
$writer->startElement('file');
$writer->writeAttribute('original', $argv[1]);
$writer->writeAttribute('source-language', $argv[2]);
$writer->writeAttribute('target-language', $argv[4]);
$writer->writeAttribute('datatype', 'xml');

$writer->startElement('body');

// Various Counters
$totalStrings = 0;
$notTranslatedStrings = array();
$totalWordCount = 0;
$notTranslatedWordCount = 0;

// Initialize counters for paired inline elements and placeholders
$currentTagId = 0;

// Trans-unit counter
$transUnitId = 0;

// Read until we reach a 'trans-unit' element
while ($reader->read() && $reader->name !== 'trans-unit');

// For each 'trans-unit' element
while ($reader->name == 'trans-unit')
{
	// LionBridge doesn't use "x-gettext-domain-header" element
	if($reader->getAttribute('restype') === "x-gettext-domain-header")
	{
		$reader->next('trans-unit');
		continue;
	}

	// Retrieve a DOMnode object from the current node so that we can access its children
	$node = $reader->expand();

	// Get source string
	$sourceChildIndex = $xliffFilter->getChildByName($node, "source");
	
	if (!is_null($sourceChildIndex)) 
	{
		// Retrieve the source string
		$sourceStr = $node->childNodes->item($sourceChildIndex)->textContent;
		
		$start = microtime(true);
		
		echo "Source @ " . microtime(true) . "= \"$sourceStr\"\n";
		
		// Apply XLIFF format to the source string
		$sourceSegments = $xliffFilter->format($sourceStr);
		
		// Checks translation status
		$approvedTranslation = false;
		
		// Get target only if approved (skips fuzzy strings in output)
		$targetChildIndex = $xliffFilter->getChildByName($node, "target");
		if (!is_null($targetChildIndex)) 
		{
			// Retrieve target string
			$targetStr = $node->childNodes->item($targetChildIndex)->textContent;
			
			// Apply XLIFF format to target string
			$targetSegments = $xliffFilter->format($targetStr);
					
			if($reader->getAttribute('approved') === 'yes')
			{
				$approvedTranslation = true;
			}
			else
			{
				// Append the string id to the list of untranslated strings
				if (!in_array($reader->getAttribute('id'), $notTranslatedStrings))
					$notTranslatedStrings[] = $reader->getAttribute('id');
			}

		}
		$destStrState = 'translated';
		
		$end = microtime(true);
		$timeDiff = $end - $start;
		echo "Took " . $timeDiff . " ms\n-------";
		
		$idSuffix = 1;
		
		// Update total strings and words counters
		$totalStrings++;
		$effectiveWordCount = 0;

		// Retrieve the slices
		foreach ($sourceSegments as $id => $unit)
		{
			$transUnitId++;
			$sourceStr = $unit;
			$segmentWordCount = str_word_count($sourceStr);
			// echo "Segment $id with $segmentWordCount words : \"$sourceStr\"\n";
							
			if (($id % 2 !== 0 && count($sourceSegments) > 1)
				|| ($id === 0 && count($sourceSegments) === 1))
			{
				// We're in a translatable unit
				$effectiveWordCount += $segmentWordCount;
			
				// Add a translatable unit in the ouput file
				$writer->startElement('trans-unit');
				// Add specific LionBridge attributes 
				$writer->writeAttribute('resname', 'CKLS' . $reader->getAttribute('id') . '_' . count($sourceSegments) . '_' . $idSuffix);
				$writer->writeAttribute("xml:space", $reader->getAttribute('xml:space'));
				$writer->writeAttribute('id', $transUnitId);
				$writer->writeAttribute('translate', 'yes');
			

				// If the translation is not approved, the target string is the same as the source string
				$targetStr = $sourceStr;
				$destStrState = 'needs-translation';
				
				if ($approvedTranslation)
				{
					// Pick the same segment amongst the target ones
					$targetStr = $targetSegments[$id];
					$destStrState = 'translated';
				}
				else
				{
					// Update untranslated words counter
					$notTranslatedWordCount += $segmentWordCount;
				}
						
				// Update total word count
				$totalWordCount += $effectiveWordCount;
				
				// Write the content of the source tag
				// Add source to output
				$writer->startElement('source');
				$writer->writeAttribute('xml:lang', $argv[2]);
				$writer->writeRaw($sourceStr);
				$writer->endElement();
				
				// Add target to output
				$writer->startElement('target');
				$writer->writeAttribute('xml:lang', $argv[4]);
				$writer->writeAttribute('state', $destStrState);
				$writer->writeRaw($targetStr);
				$writer->endElement();
				
				// Copy developer's notes into <note> tags
				$noteChildIndex = XliffFilter::getChildByName($node, "note");
				if (!is_null($noteChildIndex)) 
				{
					$noteNodeContent = $node->childNodes->item($noteChildIndex)->textContent;
					$writer->startElement('note');
					$writer->writeAttribute('from', 'developer');
					$writer->text($noteNodeContent);
					$writer->endElement();
				}
				$writer->endElement();
			}
			else
			{
				// Add a non-translatable unit in the ouput file
				// $writer->startComment();
				$writer->startElement('trans-unit');
				// Add specific LionBridge attributes 
				$writer->writeAttribute('resname', 'CKLS' . $reader->getAttribute('id') . '_' . count($sourceSegments) . '_' . $idSuffix);
				$writer->writeAttribute("xml:space", $reader->getAttribute('xml:space'));
				$writer->writeAttribute('id', $transUnitId);
				$writer->writeAttribute('translate', 'no');
				
				// Add source to output
				$writer->startElement('source');
				$writer->writeAttribute('xml:lang', $argv[2]);
				$writer->writeRaw($sourceStr);

				$writer->endElement();
				$writer->endElement();
				// $writer->endComment();
			}
			
			$idSuffix++;
			// echo "effectiveWordCount = $effectiveWordCount TotalWords = $totalWordCount, not translated = $notTranslatedWordCount\n";
			// echo "\n_____\n";
		}

		echo "\n______\n";
		// Keep on reading!
		$reader->next('trans-unit');		
	}
}
// Writes LionBridge Xliff file
$writer->fullEndElement();
$writer->endDocument();
$output = $writer->outputMemory();
$output = preg_replace("/&lt;(?=\/?(ph|ept|bpt))(.+?)&gt;/", "<$2>", $output); // This regex can be improved
file_put_contents($outputFileName, $output);

// Prepare output
$nbOfNotTranslatedStr = count($notTranslatedStrings);
$notTranslatedStrings = implode(", ", $notTranslatedStrings);

// Adapt the output with respect to the fifth argument
if (empty($argv[5]))
{
	echo "================================================\n";
	echo "Output of LionBridge Xliff file $outputFileName successful!\n";
	echo "Total strings: $totalStrings\n";
	echo "Nb of not translated strings: $nbOfNotTranslatedStr\n";
	echo "Not translated strings: $notTranslatedStrings\n";
	echo "Total word count (source): $totalWordCount\n";
	echo "Not translated word count (source): $notTranslatedWordCount\n";
}
else
{
	// CSV output
	echo "$outputFileName;$totalStrings;$nbOfNotTranslatedStr;$totalWordCount;$notTranslatedWordCount;\n";
}

$reader->close();

?>