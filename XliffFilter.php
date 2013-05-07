<?php
class XliffFilter
{
	private $currentXliffTagName;
	private $currentXliffTagId;
	private $fullStack;
	private $verificationStack;
	private $translationUnits;
	private $layoutTags = array('html', 'head', 'meta', 'body', 'p', 'table', 'tr', 'td', 'ul', 'li');
	
	// Main regex, matches html tags and placeholders
	private $regex = "/<[^>]+>|{\w+}|%\w+%?/";
	private	$layoutTagsRegex = "/<\/?(html|head|meta|body|p|table|tr|td|ul|li)[^>]*>/";
	
	public function __construct()
	{
		$this->initStacks();
		$this->translationUnits = array();
	}
	private function initStacks()
	{
		$this->fullStack = array();
		$this->verificationStack = array();
	}
	
	public function getRegex()
	{
		return $this->regex;
	}
	
	private function &getVerificationStack()
	{
		return $this->verificationStack;
	}

	private function &getFullStack()
	{
		return $this->fullStack;
	}
	
	public function getCurrentXliffTagName()
	{
		return $this->currentXliffTagName;
	}
	
	public function getCurrentXliffTagId()
	{
		return $this->currentXliffTagId;
	}

	/**
	 * Updates the current XLIFF tag 
	 *
	 *
	 * @param	$tagName	the XLIFF tag name
	 * @param	$id			the XLIFF id
	 */
	public function updateCurrentXliffTag($tagName, $id) 
	{
		$this->currentXliffTagName = $tagName;
		$this->currentXliffTagId = $id;
	}
	
	/**
	 * Returns the appropriate opening XLIFF tag
	 *
	 * @return	the opening XLIFF tag in raw text
	 */
	public function getOpeningXliffTag()
	{
		return '<' . $this->getCurrentXliffTagName() . ' id="' . $this->getCurrentXliffTagId() . '">';
	}
	
	/**
	 * Returns the appropriate closing XLIFF tag
	 *
	 * @return 	the closing XLIFF tag in raw text
	 */
	public function getClosingXliffTag()
	{
		return  '</' . $this->currentXliffTagName . '>';
	}
	
	/**
	 *	Given an HTML tag in raw text, returns the 
	 *	tag name.
	 *
	 *	@param	$tag	the tag name
	 *	@return			the HTML tag in raw text
	 */
	private function getTagName($tag) 
	{

		$matches = array();
		
		// The input can be either an HTML tag, or a placeholder (which can be surrounded by percent signs or curly brackets)
		// We use different regexes according to the input tag type
		switch ($tag[0])
		{
			case '<':
				$regex = "/[^<>\/ ]+/";
				break;
			case '{':
				$regex = "/[^{} ]+/";
				break;	
			case '%':
				$regex = "/[^% ]+/";
				break;
		}
		
		// Apply the matching
		if (preg_match($regex, $tag, $matches)) 
		{
			return $matches[0];
		}
		
		return null;
	}


	/**
	 * Format the given string by inserting the appropriate XLIFF tags
	 *
	 * @param	$string	the string we want to work on
	 */
	public function format($string) 
	{
		$this->initStacks();
		$this->translationUnits = array();
		
		$this->segment($string);
		$translationUnits = &$this->translationUnits;
		
		$array = array();
		
		foreach ($translationUnits as $unit)
		{
			// Initialize the stack for each segment
			$this->initStacks();
			
			// Build the stacks
			$this->buildStacks($unit);
			
			// Process them
			$this->processStacks();
			
			// Finally, apply the xliff tags to the string
			$str = $this->processString($unit);
			array_push($array, htmlspecialchars($str, ENT_NOQUOTES, 'UTF-8'));
		}
		
		return $array;
	}

	/**
	 * First pass, we build 2 stacks
	 * $fullStack: contains all the tags and placeholders
	 * $verificationStack: used to find opening tags
	 */
	public function buildStacks($string)
	{
		// Reinitialize the tag counter
		$openingTagCounter = 0;
		$tags = array();

		preg_match_all($this->getRegex(), $string, $tags);
		foreach($tags[0] as $match)
		{
			
			// Retrieve the tag name, it can be an HTML tag or a simple placeholder
			$tagName = $this->getTagName($match);
			
			// If we successfully retrieved the HTML tag name, proceed
			if (!is_null($tagName)) 
			{
				if (strpos($match, "/") == 1)  
				{
					// This happens to be an HTML closing tag
					// Use the verificationStack to check if there's a corresponding opening tag
					// Update the verificationStack (pop all elements in between) at the same time
					$searchResult = $this->findOpeningTag($tagName);
					
					if (is_null($searchResult))
					{
						// There is no opening HTML tag to be found
						$currentTagId = 0;
					} 
					else 
					{
						// A corresponding opening tag has been found
						// Assign the algebraic opposite of the opening tag's id to the closing tag in the stack
						$currentTagId = -$searchResult;
					}
					
					// Push the closing HTML tag onto the stack, regardless of whether it is mal-formed or not
					array_push($this->getFullStack(), array($tagName => $currentTagId));
				} 
				else 
				{
					// This can be an opening HTML tag or a placeholder
					$openingTagCounter++;
					$currentTagId = $openingTagCounter;
					
					// Push it onto both stacks
					array_push($this->getVerificationStack(), array($tagName => $currentTagId));
					array_push($this->getFullStack(), array($tagName => $currentTagId));
				}
			}
		}
	}
		
	/**
	 * Second pass
	 * We assign an id of 0 to opening tags that 
	 * don't have a corresponding closing tag. 
	 * This also applies to placeholders, since they 
	 * don't have any closing tags anyways
	 */
	public function processStacks()
	{
		// We will be working on the full stack
		$stack = &$this->getFullStack();
		
		foreach ($stack as &$tagArray)
		{
			// We are only interested in tags with positive ids (opening tags or placeholders)
			if ($tagArray[key($tagArray)] > 0)
			{
				// Retrieve the tag name
				$tagName = key($tagArray);
				if (!$this->findClosingTag($tagName, $tagArray[$tagName])) 
				{
					$tagArray[$tagName] = 0;
				}
			}
		}
	}
	
	/**
	 *	Third pass
	 *	Read the input string while shifting the full stack
	 * 	and apply the appropriate xliff tags
	 */
	public function addXliffTags($string)
	{
		
	}
	
	public function segment($string)
	{
		$cut = false;
		
		if (preg_match($this->layoutTagsRegex, $string))
		{
			$cut = true;
		}
		
		$marks = array();
		$translatable = false;
		
		if ($cut)
		{
			// Cut then push to translation units
			$temp = trim((preg_replace($this->layoutTagsRegex, '|', $string)));
			$array = explode('|', $temp);

			$marks = array();
			
			$offset = 0;
			
			foreach ($array as $el)
			{
				if (trim($el) !== '')
				{
					$temp = trim(preg_replace($this->getRegex(), '', $el));
					if ($temp !== '') 
					{
						$pos = strpos($string, $el, $offset);
						$length = strlen($el);
						array_push($marks, array($pos => $length));
						$offset += $length;
					}
				}
			}
			
			$st = 0;
			foreach ($marks as $mark)
			{
				$pos = key($mark);
				$length = $mark[$pos];
				$formatting = substr($string, $st, $pos - $st);
				$translatable = substr($string, $pos, $length);
				array_push($this->translationUnits, $formatting);
				array_push($this->translationUnits, $translatable);
				$st = $pos + $length;
			}
			$formatting = substr($string, $st);
			array_push($this->translationUnits, $formatting);
		}
		else
		{
			// Send whole string to wrapper 
			array_push($this->translationUnits, $string);
		}
	}
	
	public function processString($string)
	{
		// Initalize ph counter
		$phTagCounter = 0;
		// String offset, used for progressively matching the regex
		$offset = 0;
		
		$matches = array();
		while (preg_match($this->getRegex(), $string, $matches, 0, $offset)) 
		{
			// Retrieve the tag name
			$tagName = $this->getTagName($matches[0]);
			$pos = strpos(substr($string, $offset), $matches[0]);
			
			// If we successfully retrieved the tag name, proceed
			if (!is_null($tagName)) 
			{
				// Pop out the first full stack element to check with the string 
				$stackTagArray = array_shift($this->getFullStack());
				$stackTag = key($stackTagArray);
				$stackTagId = $stackTagArray[$stackTag];
				
				if ($stackTagId == 0) 
				{
					// It's a simple placeholder, or a mal-formed HTML tag, so we use the xlf tag PH
					$phTagCounter++;
					$this->updateCurrentXliffTag("ph", $phTagCounter);
				}
				else if ($stackTagId > 0)
				{
					// It's an opening HTML tag, so we use the xlf tag BPT
					$this->updateCurrentXliffTag("bpt", $stackTagId);
				}
				else if ($stackTagId < 0)
				{
					// It's a closing HTML tag, so we use the xlf tag EPT
					$this->updateCurrentXliffTag("ept", -$stackTagId);
				}
				
				// Surround the HTML tag with the corresponding XLIFF tags (<bpt> or <ept>)
				$string = $this->wrapTag($string, $offset, $matches[0]);
				
				// Update offset
				$offset = $offset 
						+ strlen($this->getOpeningXliffTag() . $this->getClosingXliffTag()) 
						+ strlen($matches[0]) 
						+ $pos;
						
			}
		}
		return $string;
	}
	
	/**
	 *	Finds a tag (by its name) in a given tag stack
	 *
	 *	@param	$array	the stack, in array form
	 * 			$tag 	the tag we are looking for
	 *	@return			true if the tag can be found, false otherwise
	 */
	private function arrayHasTag($array, $tag) 
	{
		$result = false;
		foreach ($array as $subarray) 
		{
			$result |= array_key_exists($tag, $subarray);
		}
		return $result;
	}

	/**
	 *	Pop elements from the tag stack until we get 
	 *	to the specified tag
	 *
	 *	@param	$tag 	the tag that we want
	 *	@return			the paired tag id corresponding to the tag,
	 *					null if it cannot be found
	 */
	private function findOpeningTag($tag) 
	{
		// We will work on the verification stack
		$stack = &$this->getVerificationStack();
		
		// We don't want to pop the stack forever...
		$maxPopTimes = count($stack);
		
		// For safety measures, we check that we can find the tag in the stack
		if ($this->arrayHasTag($stack, $tag)) 
		{
			for ($i = 1; $i <= $maxPopTimes; $i++) 
			{
				$result = array_pop($stack);
				// Compare the popped element with the tag
				if (key($result) == $tag) 
				{
					$currentPtArray = $result;
					$tagId = $currentPtArray[$tag];
					break;
				}
			}
		} 
		else 
		{
			// No tag to be found
			$tagId = null;
		}
		return $tagId;
	}

	/**
	 *	Find the corresponding closing tag
	 *	of a given opening tag with an id
	 *
	 *	@param	$tag 	the opening tag
	 *			$id		the opening tag's id
	 *	@return			true if a closing tag can be found, 
	 *					false otherwise
	 */
	private function findClosingTag($tag, $id) 
	{
		// We will be working on the full stack
		$stack = &$this->getFullStack();
		
		// Loop through all the elements
		foreach ($stack as $subStack) 
		{
			$currentStackTag = key($subStack);
			$currentStackTagId = $subStack[$currentStackTag];
			// If we find an element with the same tag name and the opposite id, success!
			if ($currentStackTagId < 0 && $currentStackTag == $tag && abs($currentStackTagId) == $id) {
				return true;
			}
			
		}
		return false;
	}


	/**
	 * 	Wrap the HTML tag with the appropriate <bpt> or <ept> tags in the 
	 *	working string array
	 *
	 * 	@param	$string			the string we are working on
	 *			$htmlTag		the HTML tag, in raw text
	 *			$xliffTagName	"ept" or "bpt"
	 *			$xliffTagId		the xliff tag id
	 *	@return					the working string array
	 */
	private function wrapTag($string, $offset, $htmlTag) 
	{
		// Where the xlf tags will be inserted
		$pos = strpos(substr($string, $offset), $htmlTag);
		
		// Compute necessary padding due to the added tags
		$htmlTagLength = strlen($htmlTag);
		$padding = strlen($this->getOpeningXliffTag() . $this->getClosingXliffTag()) + $htmlTagLength;

		// Insert the opening and closing XLIFF tags
		$string = substr_replace($string, $this->getClosingXliffTag(), $pos + $offset + $htmlTagLength, 0);
		$string = substr_replace($string, $this->getOpeningXliffTag(), $pos + $offset, 0);

		return $string;
	}

	/**
	 *	Print out the child list of a given DOMNode object
	 *
	 *	@param	$node	a DOMNode object
	 */
	public function printChildList($node) 
	{
		echo "Child list for node " . $node->nodeName . ": \n";
		for ($i = 0; $i < $node->childNodes->length; $i++) 
		{
			print "\tChild no. $i : " . $node->childNodes->item($i)->nodeName . "\n";
		}
	}

	/**
	 *	Get a child node from a DOMNode object by its name
	 *
	 *	@param	$node	a DOMNode object
	 *	@return 		the child node as a DOMNode object if it can be found,
	 *					NULL otherwise
	 */
	public function getChildByName($node, $childName) 
	{
		if ($node !== false)
		{
			for ($i = 0; $i < $node->childNodes->length; $i++) 
			{
				$currentChild = $node->childNodes->item($i)->nodeName;
				if ($currentChild == $childName) 
				{
					return $i;
				}
			}
			return null;
		}
		return null;
	}

}
?>