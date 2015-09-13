<?php
class LookupTests extends WP_UnitTestCase {
	const SAMPLE_LOOKUP_LANG = 'sample';

	private $_sampleLookupData = array();
	
	private function _getEnv() {
		return Abp01_Env::getInstance();
	}
	
	private function _getDb() {
		return $this->_getEnv()->getDb();
	}

	private function _newFakeLabel() {
		$faker = Faker\Factory::create();
		return $faker->words(3, true);
	}

	private function _newFakeIntNumber($min = 0, $max = PHP_INT_MAX) {
		$faker = Faker\Factory::create();
		return $faker->numberBetween($min, $max);
	}

	private function _getSupportedLookupCategories() {
		return array(
			Abp01_Lookup::BIKE_TYPE => false,
			Abp01_Lookup::DIFFICULTY_LEVEL => false,
			Abp01_Lookup::PATH_SURFACE_TYPE => false,
			Abp01_Lookup::RAILROAD_ELECTRIFICATION => false,
			Abp01_Lookup::RAILROAD_LINE_STATUS => false,
			Abp01_Lookup::RAILROAD_LINE_TYPE => true,
			Abp01_Lookup::RAILROAD_OPERATOR => true,
			Abp01_Lookup::RECOMMEND_SEASONS => true
		);
	}
	
	private function _getLookupTypeWithoutPostAssociation() {
		foreach ($this->_sampleLookupData as $category => $data) {
			if (!$data['has_post']) {
				return $category;
			}
		}
	}

	private function _getLookupItemWithoutPostAssociation() {
		foreach ($this->_sampleLookupData as $category => $data) {
			if (!$data['has_post']) {
				return $data;
			}
		}
	}

	private function _getLookupItemWithPostAssociation() {
		foreach ($this->_sampleLookupData as $category => $data) {
			if ($data['has_post']) {
				return $data;
			}
		}
	}

	private function _getRandomLookupType() {
		$supportedTypes = array_keys($this->_getSupportedLookupCategories());
		shuffle($supportedTypes);
		return $supportedTypes[0];
	}

	private function _installTestData() {
		$db = $this->_getDb();
		$table = $this->_getEnv()->getLookupTableName();
		$langTable = $this->_getEnv()->getLookupLangTableName();
		$faker = Faker\Factory::create();

		//install test lookup data
		foreach ($this->_getSupportedLookupCategories() as $category => $associateWithPost) {
			$newName = $faker->words(3, true);
			$newNameLang = $faker->words(3, true);

			$newId = $db->insert($table, array(
				'lookup_category' => $category,
				'lookup_label' => $newName
			));

			if ($newId) {
				$db->insert($langTable, array(
					'ID' => $newId,
					'lookup_label' => $newNameLang,
					'lookup_lang' => self::SAMPLE_LOOKUP_LANG,
				));
				$this->_sampleLookupData[$category] = array(
					'data_id' => $newId,
					'data_label' => $newName,
					'data_label_lang' => $newNameLang,
					'data_category' => $category, 
					'has_post' => $associateWithPost
				);
				if ($associateWithPost) {
					$this->_createLookupAssociation($newId);
				}
			}
		}
	}

	private function _createLookupAssociation($lookupId) {
		$db = $this->_getDb();
		$db->insert($this->_getEnv()->getRouteDetailsLookupTableName(), array(
			'post_ID' => $this->_newFakeIntNumber(),
			'lookup_ID' => $lookupId
		));
	}

	private function _clearTestData() {
		$db = $this->_getDb();
		$table = $this->_getEnv()->getLookupTableName();
		$langTable = $this->_getEnv()->getLookupLangTableName();
		$db->rawQuery('TRUNCATE TABLE `' . $table . '`', null, false);
		$db->rawQuery('TRUNCATE TABLE `' . $langTable . '`', null, false);
		$this->_sampleLookupData = array();
	}

	private function _getLookup($lookupId) {
		$db = $this->_getDb();
		$table = $this->_getEnv()->getLookupTableName();
		$db->where('ID', $lookupId);
		return $db->getOne($table);
	}

	private function _getLookupTranslations($lookupId, $lang = null) {
		$db = $this->_getDb();
		$table = $this->_getEnv()->getLookupLangTableName();
		$db->where('ID', $lookupId);
		if ($lang) {
			$db->where('lookup_lang', $lang);
			return $db->getOne($table);
		}
		return $db->get($table);
	}

	private function _assertLookupMatchesSampleData(stdClass $result, array $data, $useLang) {
		$this->assertNotNull($result);
		if  (!$useLang) {
			$this->assertEquals($data['data_label'], $result->label);
		} else {
			$this->assertEquals($data['data_label_lang'], $result->label);
		}
		$this->assertEquals($data['data_id'], $result->id);
		$this->assertEquals($data['data_category'], $result->type);
	}

	private function _assertLookupTranslationMatchesLabel($lookupId, $lang, $label) {
		$itemTranslation = $this->_getLookupTranslations($lookupId, $lang);
		$this->assertNotEmpty($itemTranslation);
		$this->assertEquals($label, $itemTranslation['lookup_label']);
	}

	private function _assertLookupItemTranslationMissing($lookupId, $lang) {
		$itemTranlation = $this->_getLookupTranslations($lookupId, $lang);
		$this->assertNull($itemTranlation);
	}

	public function setUp() {
		parent::setUp();
		$this->_installTestData();
	}

	public function tearDown() {
		parent::tearDown();
		$this->_clearTestData();
	}

	public function testCanReadExistingLookup_genericRead_defaultLang() {
		$lookup = new Abp01_Lookup();
		foreach ($this->_sampleLookupData as $category => $data) {
			$result = $lookup->lookup($category, $data['data_id']);
			$this->_assertLookupMatchesSampleData($result, $data, false);
		}
	}

	public function testCanReadExistingLookup_genericRead_sampleLang() {
		$lookup = new Abp01_Lookup(self::SAMPLE_LOOKUP_LANG);
		foreach ($this->_sampleLookupData as $category => $data) {
			$result = $lookup->lookup($category, $data['data_id']);
			$this->_assertLookupMatchesSampleData($result, $data, true);
		}
	}

	public function testCanCreateLookupItem() {
		$label = $this->_newFakeLabel();
		$type = $this->_getRandomLookupType();
		
		$lookup = new Abp01_Lookup();
		$item = $lookup->createLookupItem($type, $label);
		
		$this->assertNotNull($item);
		$this->assertEquals($label, $item->label);
		$this->assertEquals($type, $item->type);
		$this->assertGreaterThan(0, $item->id);
	}

	public function testCanModifyLookupItem() {
		$lookup = new Abp01_Lookup();
		$type = $this->_getRandomLookupType();
		$existingItemId = $this->_sampleLookupData[$type]['data_id'];
		$newLabel = $this->_newFakeLabel();

		$lookup->modifyLookupItem($existingItemId, $newLabel);
		$item = $lookup->lookup($type, $existingItemId);

		$this->assertNotNull($item);
		$this->assertEquals($newLabel, $item->label);
		$this->assertEquals($type, $item->type);
		$this->assertEquals($existingItemId, $item->id);
	}

	public function testCanDeleteLookupItem() {
		$lookup = new Abp01_Lookup();
		$type = $this->_getLookupTypeWithoutPostAssociation();
		$existingItemId = $this->_sampleLookupData[$type]['data_id'];

		$lookup->deleteLookup($existingItemId);
		$item = $this->_getLookup($existingItemId);
		$itemLangs = $this->_getLookupTranslations($existingItemId);
		
		$this->assertEmpty($item);
		$this->assertEmpty($itemLangs);
	}

	public function testCanCreateLookupItemTranslation() {
		$lang = 'fr';
		$newLabel = $this->_newFakeLabel();
		$type = $this->_getRandomLookupType();
		$existingItemId = $this->_sampleLookupData[$type]['data_id'];

		$lookup = new Abp01_Lookup();
		$result = $lookup->addLookupItemTranslation($existingItemId, $lang, $newLabel);

		$this->assertTrue($result);
		$this->_assertLookupTranslationMatchesLabel($existingItemId, $lang, $newLabel);
	}

	public function testCanModifyLookupItemTranslation() {
		$newLabel = $this->_newFakeLabel();
		$type = $this->_getRandomLookupType();
		$existingItemId = $this->_sampleLookupData[$type]['data_id'];
		$existingItemLang = self::SAMPLE_LOOKUP_LANG;

		$lookup = new Abp01_Lookup();
		$result = $lookup->modifyLookupItemTranslation($existingItemId, $existingItemLang, $newLabel);

		$this->assertTrue($result);
		$this->_assertLookupTranslationMatchesLabel($existingItemId, $existingItemLang, $newLabel);
	}

	public function testCanDeleteLookupItemTranslation() {
		$type = $this->_getRandomLookupType();
		$existingItemId = $this->_sampleLookupData[$type]['data_id'];
		$existingItemLang = self::SAMPLE_LOOKUP_LANG;

		$lookup = new Abp01_Lookup();
		$result = $lookup->deleteLookupItemTranslation($existingItemId, $existingItemLang);
		
		$this->assertTrue($result);
		$this->_assertLookupItemTranslationMissing($existingItemId, $existingItemLang);
	}

	public function testCanCheckIfLookupItemInUse_itemInUse() {
		$item = $this->_getLookupItemWithPostAssociation();
		$lookup = new Abp01_Lookup();
		$this->assertTrue($lookup->isLookupInUse($item['data_id']));
	}

	public function testCanCheckIfLookupItemInUse_itemNotInUse() {
		$item = $this->_getLookupItemWithoutPostAssociation();
		$lookup = new Abp01_Lookup();
		$this->assertFalse($lookup->isLookupInUse($item['data_id']));
	}
}