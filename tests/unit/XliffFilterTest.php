<?php
require_once("XliffFilter.php");

/**
 * @covers XliffFilter
 */
class XliffFilterTest extends PHPUnit_Framework_TestCase
{
  /**
   * @covers 
   */
	public function testConstructor()
	{
		$filter = new XliffFilter();
		
		return $filter;
	}
	
	/**
	 * @covers updateCurrentXliffTag
	 * @depends testConstructor
	 */
	public function testUpdateCurrentXliffTag(XliffFilter $filter)
	{
		$filter->updateCurrentXliffTag('ph', 1);
		$this->assertEquals('ph', $filter->getCurrentXliffTagName());
		$this->assertEquals(1, $filter->getCurrentXliffTagId());
		
		return $filter;
	}
	
	/**
	 * @covers getOpeningXliffTag
	 * @depends testUpdateCurrentXliffTag
	 */
	public function testGetOpeningXliffTag(XliffFilter $filter)
	{
		$this->assertEquals('<ph id="1">', $filter->getOpeningXliffTag());
	}
	
	/**
	 * @covers getClosingXliffTag
	 * @depends testUpdateCurrentXliffTag
	 */
	public function testGetClosingXliffTag(XliffFilter $filter)
	{
		$this->assertEquals('</ph>', $filter->getClosingXliffTag());
	}
	/**
	 * @covers extractElementName
	 * @depends testConstructor
	 */
	public function testExtractElementName(XliffFilter $filter)
	{
		$this->assertEquals('html', $filter->extractElementName('<html>'));
		$this->assertEquals('placeholder', $filter->extractElementName('{placeholder}'));
		$this->assertEquals('1', $filter->extractElementName('%1'));
		$this->assertEquals('2', $filter->extractElementName('%2%'));
	}
}

?>
