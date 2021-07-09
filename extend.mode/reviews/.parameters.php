<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();
/** @var array $arCurrentValues */

if(!CModule::IncludeModule("iblock"))
	return;

	$arTypesEx = CIBlockParameters::GetIBlockTypes(array("-"=>" "));

	$arIBlocks=array();
	$db_iblock = CIBlock::GetList(
		array("SORT"=>"ASC"),
		array(
			"SITE_ID"=>$_REQUEST["site"],
		)
	);
	while($arRes = $db_iblock->Fetch())
	{
		$arIBlocks[$arRes["ID"]] = $arRes["NAME"];
	}


	$arProperty_LNS = array();
	$rsProp = CIBlockProperty::GetList(
		array("sort"=>"asc", "name"=>"asc"),
		array(
			"ACTIVE"=>"Y",
			"IBLOCK_ID"=>(isset($arCurrentValues["IBLOCK_ID"])?$arCurrentValues["IBLOCK_ID"]:$arCurrentValues["ID"])
		)
	);
	while ($arr=$rsProp->Fetch())
	{
		$arProperty[$arr["CODE"]] = "[".$arr["CODE"]."] ".$arr["NAME"];
		if (in_array($arr["PROPERTY_TYPE"], array("L", "N", "S")))
		{
			$arProperty_LNS[$arr["CODE"]] = "[".$arr["CODE"]."] ".$arr["NAME"];
		}
	}


$arComponentParameters = array(
	"GROUPS" => array(
	),
	"PARAMETERS" => array(
		"IBLOCK_ID" => array(
			"PARENT" => "BASE",
			"NAME" => GetMessage("EX_MODE_IBLOCK_ID"),
			"TYPE" => "LIST",
			"VALUES" => $arIBlocks,
			"ADDITIONAL_VALUES" => "Y",
			"REFRESH" => "Y",
		),
        "SORT_DATE" => array(
            "PARENT" => "BASE",
            "NAME" => GetMessage("EX_MODE_SORT_DATE"),
            "TYPE" => "LIST",
            "VALUES" => ['ASC' => 'По возростанию', 'DESC' => 'По убыванию']
        ),
        "ELEMENT_ID" => array(
            "PARENT" => "BASE",
            "NAME" => GetMessage("EX_MODE_ELEMENT_ID"),
            "TYPE" => "TEXT",
            "DEFAULT" => '={$_REQUEST["ELEMENT_ID"]}',
        ),
        "LINK_IBLOCK_ID" => array(
            "PARENT" => "BASE",
            "NAME" => GetMessage("EX_MODE_LINK_IBLOCK_ID"),
            "TYPE" => "LIST",
            "VALUES" => $arIBlocks,
            "ADDITIONAL_VALUES" => "Y",
            "REFRESH" => "Y",
        ),
        "MAX_LENGTH_MESSAGE" => array(
            "PARENT" => "BASE",
            "NAME" => GetMessage("EX_MODE_MAX_LENGTH_MESSAGE"),
            "TYPE" => "TEXT",
            "DEFAULT" => "1000",
        ),
        "TIME_COOL_DOWN" => array(
            "PARENT" => "BASE",
            "NAME" => GetMessage("EX_MODE_TIME_COOL_DOWN"),
            "TYPE" => "TEXT",
            "DEFAULT" => "60",
        ),
        "REQUIRED_FIELDS" => array(
            "PARENT" => "BASE",
            "NAME" => "Обязательные поле",
            "TYPE" => "LIST",
            "MULTIPLE" => "Y",
            "VALUES" => [
                'email' =>'email',
                'phone'=>'phone',
                'rang'=>'rang',
                'file'=>'file'
            ],
            "DEFAULT" => [
                'email' =>'email',
                'rang'=>'rang',
            ]
        ),
        "SHOW_FIELDS" => array(
            "PARENT" => "BASE",
            "NAME" => "Показать поля",
            "TYPE" => "LIST",
            "MULTIPLE" => "Y",
            "VALUES" => [
                'email' =>'email',
                'phone'=>'phone',
                'rang'=>'rang',
                'file'=>'file'
            ],
            "DEFAULT" => [
                'email' =>'email',
                'rang'=>'rang',
            ]
        ),
        "MAX_DEPTH" => array(
            "PARENT" => "BASE",
            "NAME" => GetMessage("EX_MODE_MAX_DEPTH"),
            "TYPE" => "TEXT",
            "DEFAULT" => "3",
        ),
        "FIELD_SORT_NAME" => array(
            "PARENT" => "BASE",
            "NAME" => "Сортировка name",
            "TYPE" => "STRING",
            "DEFAULT" => 2,
        ),
        "FIELD_SORT_TEXT" => array(
            "PARENT" => "BASE",
            "NAME" => "Сортировка text",
            "TYPE" => "STRING",
            "DEFAULT" => 1,
        ),
        "FIELD_SORT_EMAIL" => array(
            "PARENT" => "BASE",
            "NAME" => "Сортировка email",
            "TYPE" => "STRING",
            "DEFAULT" => 3,
        ),
        "FIELD_SORT_PHONE" => array(
            "PARENT" => "BASE",
            "NAME" => "Сортировка phone",
            "TYPE" => "STRING",
            "DEFAULT" => 4,
        ),
        "FIELD_SORT_RANG" => array(
            "PARENT" => "BASE",
            "NAME" => "Сортировка rang",
            "TYPE" => "STRING",
            "DEFAULT" => 0,
        ),
        "FIELD_SORT_FILE" => array(
            "PARENT" => "BASE",
            "NAME" => "Сортировка file",
            "TYPE" => "STRING",
            "DEFAULT" => 5,
        ),
        "EVENT_ID" => array(
            "PARENT" => "BASE",
            "NAME" => GetMessage("EX_MODE_EVENT_ID"),
            "TYPE" => "TEXT",
            "DEFAULT" => "NEW_COMMENT",
        ),
        "EVENT_USER_ID" => array(
            "PARENT" => "BASE",
            "NAME" => GetMessage("EX_MODE_EVENT_USER_ID"),
            "TYPE" => "TEXT",
            "DEFAULT" => "NEW_COMMENT_USER",
        ),
        "CACHE_TIME" => array(
            "PARENT" => "BASE",
            "NAME" => GetMessage("EX_MODE_CACHE_TIME"),
            "TYPE" => "TEXT",
            "DEFAULT" => "3600000",
        ),
	),
);




