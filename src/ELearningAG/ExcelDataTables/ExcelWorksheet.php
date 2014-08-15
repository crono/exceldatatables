<?php

namespace ELearningAG\ExcelDataTables;

/**
 * An instance of this class represents a simple(!) ExcelWorkseeht in the spreadsheetml format.
 * The most important function ist the addRow() function which takes an array as parameter and
 * adds its values to the worksheet. Finally the worksheet can be exported to XML using the toXML()
 * method
 *
 * @author Severin Neumann <s.neumann@elearning-ag.de>
 * @copyright 2014 die eLearning AG
 * @license GPL-3.0 
 */
class ExcelWorksheet
{
		/**
		 * This namespaces are used to setup the XML document.
		 *
		 * @var array 
		 */
		protected static $namespaces = array(
				"spreadsheets" => "http://schemas.openxmlformats.org/spreadsheetml/2006/main",
				"relationships" => "http://schemas.openxmlformats.org/officeDocument/2006/relationships",
				"xmlns" => "http://www.w3.org/2000/xmlns/"
		);

		/**
		 * The base date which is used to compute date field values
		 *
		 * @var string
		 */
		protected static $baseDate = "1899-12-31 00:00:00";

		protected static $fixDays = 1;

		/**
		 * The XML base document
		 *
		 * @var \DOMDocument
		 */
		protected $document;

		/**
		 * The worksheet element. This is the root element of the XML document
		 *
		 * @var \DOMElement
		 */
		protected $worksheet;
		/**
		 * The sheetData element. This element contains all rows of the spreadsheet
		 *
		 * @var \DOMElement
		 */
		protected $sheetData;

		/**
		 * The formatId used for date and time values. The correct id is specified
		 * in the styles.xml of a workbook. The default value 1 is a placeholder
		 *
		 * @var int
		 */
		protected $dateTimeFormatId = 1;

		protected $dateTimeColumns = array();


		protected $rowCounter = 1;

		/**
		 * Setup a default document: XML head, Worksheet element, SheetData element.
		 *
		 * @return $this
		 */
		public function setupDefaultDocument() {
				$this->getSheetData();
				return $this;
		}

		/**
		 * Change the formatId for date time values.
		 *
		 * @return $this
		 */
		public function setDateTimeFormatId($id) {
				$this->dateTimeFormatId = $id;
				foreach($this->dateTimeColumns as $column) {
					$column->setAttribute('s', $id);
				}
				return $this;
		}

		/**
		 * Convert DateTime to excel time format. This function is
		 * a copy from PHPExcel.
		 *
		 * @see https://github.com/PHPOffice/PHPExcel/blob/78a065754dd0b233d67f26f1ef8a8a66cd449e7f/Classes/PHPExcel/Shared/Date.php
		 */
		public static function convertDate(\DateTimeInterface $date) {

				$year = $date->format('Y');
				$month = $date->format('m');
				$day = $date->format('d');
				$hours = $date->format('H');
				$minutes = $date->format('i');
				$seconds = $date->format('s');

				$excel1900isLeapYear = TRUE;
				if (($year == 1900) && ($month <= 2)) { $excel1900isLeapYear = FALSE; }
				$my_excelBaseDate = 2415020;
				if ($month > 2) {
						$month -= 3;
				} else {
						$month += 9;
						$year -= 1;
				}
				// Calculate the Julian Date, then subtract the Excel base date (JD 2415020 = 31-Dec-1899 Giving Excel Date of 0)
				$century = substr($year,0,2);
				$decade = substr($year,2,2);
				$excelDate = floor((146097 * $century) / 4) + floor((1461 * $decade) / 4) + floor((153 * $month + 2) / 5) + $day + 1721119 - $my_excelBaseDate + $excel1900isLeapYear;

				$excelTime = (($hours * 3600) + ($minutes * 60) + $seconds) / 86400;

				return (float) $excelDate + $excelTime;

		}

		/**
		 * By default the XML document is generated without format. This can be
		 * changed with this function.
		 *
		 * @param $value 
		 * @return $this
		 */
		public function setFormatOutput($value = true) {
				$this->getDocument()->formatOutput = true;
				return $this;
		}

		/**
		 * Returns the given worksheet in its XML representation
		 *
		 * @return string
		 */
		public function toXML() {
				$document = $this->getDocument();
				return $document->saveXML();
		}

		/**
		 * Generate and return a new empty row within the sheetData
		 *
		 * @return \DOMElement
		 */
		protected function getNewRow() {
				$sheetData = $this->getSheetData();
				$row = $this->append('row', array(), $sheetData);
				$row->setAttribute('r', $this->rowCounter++);
				return $row;			
		}

		/**
		 * Set the inner text for $element to $text. Returns the DOMNode
		 * representing the text.
		 *
		 * @return \DOMText
		 */
		protected function setText($element, $text) {
				$textElement = $this->getDocument()->createTextNode($text);
				$element->appendChild($textElement);
				return $textElement;
		}

		/**
		 * Add an inline string column to the given $row.
		 *
		 * @param \DOMElement $row
		 * @param string $column
		 * @return \DOMElement
		 */
		protected function addStringColumnToRow($row, $column) {
				$c = $this->append('c', array('t' => 'inlineStr'), $row);
				$is = $this->append('is', array(), $c);
				$t = $this->append('t', array(), $is);
				$this->setText($t, $column);
				return $t;
		}

		/**
		 * Add an number column to the given $row.
		 *
		 * @param \DOMElement $row
		 * @param int|string $column
		 * @return \DOMElement
		 */
		protected function addNumberColumnToRow($row, $column) {
				$c = $this->append('c', array(), $row);
				$v = $this->append('v', array(), $c);
				$this->setText($v, $column);
				return $v;
		}

		/**
		 * Add a date time dolumn to the given $row. $column is converted to a numerical
		 * value relativ to the static::$baseDate value
		 *
		 * @param \DOMElement $row
		 * @param \DateTimeInterface $column
		 * @return \DOMElement
		 */
		protected function addDateTimeColumnToRow($row, \DateTimeInterface $column) {
				$c = $this->append('c', array('s' => $this->dateTimeFormatId), $row);
				$this->dateTimeColumns[] = $c;
				$v = $this->append('v', array(), $c);
				$this->setText($v, static::convertDate($column) );
				return $v;
		}

		/**
		 * Add a column to a row. The type of the column is deferred by its value
		 *
		 * @param \DOMElement $row
		 * @param mixed $column
		 * @return \DOMElement
		 */
		protected function addColumnToRow($row, $column) {
				if(is_array($column) 
						&& isset($column['type']) 
						&& isset($column['value']) 
						&& in_array($column['type'], array('string', 'number', 'datetime'))) {
								$function = 'add'.ucfirst($column['type']).'ColumnToRow';
								return $this->$function($row, $column['value']);
						} elseif(is_numeric($column)) {
								return $this->addNumberColumnToRow($row, $column);
						} elseif($column instanceof \DateTimeInterface) {
								return $this->addDateTimeColumnToRow($row, $column);
						}
				return $this->addStringColumnToRow($row, (string)$column);
		}

		/**
		 * Add a row to the spreadsheet. The columns are inserted and their type is deferred by their type:
		 *
		 * - Arrays having a type and value element are inserted as defined by the type. Possible types
		 * are: string, number, datetime
		 * - Numerical values are inserted as number columns.
		 * - Objects implementing the DateTimeInterface are inserted as datetime column.
		 * - Everything else is converted to a string and inserted as (inline) string column.
		 *
		 * @param array $columns
		 * @return $this
		 */
		public function addRow($columns = array()) {
				$row = $this->getNewRow();
				foreach($columns as $column) {
						$this->addColumnToRow($row, $column);
				}
				return $this;
		}

		/**
		 * Returns the DOMDocument representation of the current instance
		 *
		 * @return \DOMDocument
		 */
		public function getDocument() {
				if(is_null($this->document)) {
						$this->document = new \DOMDocument('1.0', 'utf-8');
						$this->document->xmlStandalone = true;
				}
				return $this->document;
		}

		/**
		 * Returns the DOMElement representation of the sheet data
		 *
		 * @return \DOMElement
		 */
		public function getSheetData() {
				if(is_null($this->sheetData)) {
						$this->sheetData = $this->append('sheetData');
				}
				return $this->sheetData;
		}

		/**
		 * Crate a new \DOMElement within the scope of the current document.
		 *
		 * @param string name
		 * @return \DOMElement
		 */
		protected function createElement($name) {
				return $this->getDocument()->createElementNS(static::$namespaces['spreadsheets'], $name);
		}	

		/**
		 * Returns the DOMElement representation of the worksheet 
		 *
		 * @return \DOMElement
		 */
		public function getWorksheet() {
				if(is_null($this->worksheet)) {
						$document = $this->getDocument();
						$this->worksheet = $this->append('worksheet', array(), $document);
						$this->worksheet->setAttributeNS(static::$namespaces['xmlns'], 'xmlns:r', static::$namespaces['relationships']);
				}
				return $this->worksheet;
		}

		/**
		 * Append a new element (tag) to the XML Document. By default the new tag <$name/> will be attachted
		 * to the root element (i.e. <worksheet>). Attributes for the new tag can be specified with the second
		 * parameter $attribute. Each element of the $attributes array is added as attribute whereas the key
		 * is the attribute name and the value is the attribute value.
		 * If the new element should be appended to another parent element in the XML Document the third
		 * parameter can be used to specify the parent
		 *
		 * The function returns the newly created element as \DOMElement instance.
		 *
		 * @param string name
		 * @param array attributes
		 * @param \DOMElement parent
		 * @return \DOMElement 
		 */
		protected function append($name, $attributes = array(), $parent = null) {
				if(is_null($parent)) {
						$parent = $this->getWorksheet();
				}
				$element = $this->createElement($name);
				foreach($attributes as $key => $value) {
						$element->setAttribute($key, $value);
				}
				$parent->appendChild($element);
				return $element;
		}

		public function addRows($array)
		{
				foreach($array as $row) {
						$this->addRow($row);
				}
				return $this;
		}
}