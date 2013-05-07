<?php
require_once("XliffFilter.php");

// Check that all required arguments were passed
if (count($argv) < 5)
{
	echo 'Syntax is "php xliff2lb.php input.xlf source-language output.xlf target-language"' . "\n";
	echo 'e.g. php xliff2lb.php nl-NL_raw.xlf en-GB nl-NL.xlf nl-NL csv >> buildXliffsOutput.csv' . "\n";
	exit("Missing parameters");
}

// Initialize XMLReader
$reader = new XMLReader();

// Unwrap the comments
$tempFile = 'temp.xlf';
$xlf = file_get_contents($argv[1]);
$xlf = str_replace('<!--', '', $xlf);
$xlf = str_replace('-->', '', $xlf);
file_put_contents($tempFile, $xlf);

$reader->open($tempFile, 'UTF-8');

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

// Trans-unit counter
$transUnitId = 0;

// Various Counters
$totalStrings = 0;
$totalWordCount = 0;
$notTranslatedStrings = array();
$notTranslatedWordCount = 0;

// Buffers
$sourceBuffer = '';
$targetBuffer = '';
$noteBuffer = '';
$sourceContent = '';
$targetContent = '';
$noteContent = '';
$approvedStr = '';
$approvedBool = true;

$isCommonSegment = false;
$segmentCounter = 0;

// Read until we reach a 'trans-unit' element
while ($reader->read() && $reader->name !== 'trans-unit');


// For each 'trans-unit' element
while ($reader->name === 'trans-unit')
{
	// Extract current segment id from the resname, as well as the total number of segments for this translation-unit
	$resname = $reader->getAttribute('resname');
	$resnameSuffix = substr($resname, strpos($resname, '_') + 1);
	$totalSegments = intval(substr($resnameSuffix, 0, strpos($resnameSuffix, '_')));
	$segmentId = intval(substr($resnameSuffix, strpos($resnameSuffix, '_') + 1));
	
	echo "\n*********\nCurrently in entity with $totalSegments segments, at segment # $segmentId: \n";
	
	// Retrieve a DOMNode element from the current node
	$node = $reader->expand();
	
	// Find the source, target and note children
	$sourceNodeIndex = $xliffFilter->getChildByName($node, 'source');
	$targetNodeIndex = $xliffFilter->getChildByName($node, 'target');
	$noteNodeIndex = $xliffFilter->getChildByName($node, 'note');
	
	// Word count for each source unit
	$effectiveWordCount = 0;
	
	// If there is a source child node
	if (!is_null($sourceNodeIndex))
	{
		// Retrieve the source node content
		$sourceContent = $node->childNodes->item($sourceNodeIndex)->textContent;
			
		$destStrState = '';
		
		$segmentWordCount = str_word_count($sourceContent);
		echo "Source segment with $segmentWordCount words : \"$sourceContent\"\n\n";
		
		if ($reader->getAttribute('translate') === 'no')
		{
			// We're in a segment that must not be translated
			// We will have to append it to both source and target buffers
			$isCommonSegment = true;
			
			echo "This is an untranslatable segment\n";
		}
		else 
		{
			if (!is_null($targetNodeIndex))
			{
				// Validated translation
				$targetNode = $node->childNodes->item($targetNodeIndex);
				$targetContent = $targetNode->textContent;
				
				echo "Target segment: \"$targetContent\"\n\n";
				
				if (!is_null($targetNode->attributes->getNamedItem('state')))
				{
					$targetState = $targetNode->attributes->getNamedItem('state')->textContent;
					
					// Checks translation status
					if($targetState === 'translated')
					{
						// There's no need for translation. 
						// Either this translation-unit has really been translated,
						// or it is not translatable (i.e. only contains layout HTML tags)
						$approvedBool &= true;	// AND operator because a translation-unit is approved iff every one of its segments is approved
						$destStrState = 'translated';

					}
					else
					{
						// There's no translation available
						$approvedBool = false;
						$destStrState = 'needs-translation';
						
						// Update untranslated words counter
						$notTranslatedWordCount += $segmentWordCount;
					}
				}
			}
		}
		
		$effectiveWordCount += ($isCommonSegment) ? 0 : $segmentWordCount;
		
		
		// Retrieve the content of the 'note' node if possible
		if (!is_null($noteNodeIndex))
		{
			$noteContent = $node->childNodes->item($noteNodeIndex)->textContent;
			$noteBuffer .= $noteContent;
		}
		
		
		$totalWordCount += $effectiveWordCount;
		
		// Append contents to appropriate buffers
		$sourceBuffer .= $sourceContent;
		$targetBuffer .= ($isCommonSegment) ? $sourceContent : $targetContent;
		
		echo "\n-----------------\n";
		echo "Source buffer: \"$sourceBuffer\"\n";
		echo "Target buffer: \"$targetBuffer\"\n";
		echo "\n-----------------\n";
		
		// If we are at the last segment of a translation-unit, write out the XLF and flush the buffers
		if ($segmentId === $totalSegments
		&& $sourceBuffer !== ''
		&& $targetBuffer !== '') 
		{
			// Increment effective translation-unit id
			$transUnitId++;
			
			// Start writing trans-unit 
			$writer->startElement('trans-unit');
			
			// Add attributes to the trans-unit element
			$writer->writeAttribute("xml:space", $reader->getAttribute('xml:space'));
			$writer->writeAttribute('id', $transUnitId);
			$approvedStr = ($approvedBool) ? 'yes' : 'no';
			$writer->writeAttribute('approved', $approvedStr);
			
			// Write the content of the source element
			$writer->writeElement('source', $sourceBuffer);
			
			// Write the content of the target element
			$writer->startElement('target');
			$writer->writeAttribute('state', $destStrState);
			$writer->text($targetBuffer);
			$writer->endElement();
			
			// If there's a developer note, write it out
			// We have to include both <context-group> and <note> in order 
			// for the xliff2po function to generate comments out of it
			if ($noteBuffer !== '')
			{
				$writer->startElement('context-group');
				$writer->writeAttribute('name', 'po-entry');
				$writer->writeAttribute('purpose', 'information');
				$writer->startElement('context');
				$writer->writeAttribute('context-type', 'x-po-autocomment');
				$writer->text($noteBuffer);
				$writer->endElement();
				$writer->endElement();
				
				$writer->startElement('note');
				$writer->writeAttribute('from', 'developer');
				$writer->text($noteBuffer);
				$writer->endElement();
				
				// Flush note buffer
				$noteBuffer = '';
			}
			
			// Update total strings counter
			$totalStrings++;
			
			if (!$approvedBool)
			{
				// Append the string id to the list of untranslated strings
				if (!in_array($transUnitId, $notTranslatedStrings))
					$notTranslatedStrings[] = $transUnitId;
			}
			
			// Flush other buffers
			$sourceBuffer = '';
			$targetBuffer = '';
			
			// Reinitialize the translation approval boolean
			$approvedBool = true;
			
			// End the trans-unit
			$writer->endElement();
			
			echo "\nLast Segment !!! XML Written !!!\n";
		}
		
		echo "effectiveWordCount = $effectiveWordCount TotalWords = $totalWordCount, not translated = $notTranslatedWordCount\n";
		echo "\n*********\n";
	}
	$isCommonSegment = false;
	
	// Keep on reading!
	$reader->next('trans-unit');		
	
}


// Write XLF file
$writer->fullEndElement();
$writer->endDocument();
$output = $writer->outputMemory();
file_put_contents($outputFileName, $output);

// Close and clean
$reader->close();
exec("del $tempFile");

// Prepare output
$nbOfNotTranslatedStr = count($notTranslatedStrings);
$notTranslatedStrings = implode(", ", $notTranslatedStrings);

// Adapt the output with respect to the fifth argument
if (empty($argv[5]))
{
	echo "================================================\n";
	echo "Output of regular Xliff file $outputFileName successful!\n";
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


?>