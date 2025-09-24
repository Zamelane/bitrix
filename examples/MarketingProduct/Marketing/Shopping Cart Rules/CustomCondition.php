<?
  /* Добавление своего условия в условия применения скидки к заказу/доставки и т.д.
   * На примере добавления скидки при условии, что регион дальний (кастомное свойство в свойствах заказа. В checkRegion ищё его id и через переданную запись заказа проверяю, какое у неё значение).
  */
?>

<?php
// В init.php
require_once __DIR__ . '/lib/RemoteRegionService.php';
$eventManager->addEventHandlerCompatible(
    'sale',
    'OnCondSaleControlBuildList',
    ['Lib\RemoteRegionService', 'GetControlDescr']
);

?>

<?php
// В local/php_interface/lib/RemoteRegionService.php
namespace Lib;

use Bitrix\Sale\Internals\OrderPropsTable;

class RemoteRegionService extends \CSaleCondCtrlComplex
{

  /**
   * Получение имени класса
   * @return string
   */
  public static function GetClassName()
  {
    return __CLASS__;
  }

  /**
   * Получение ID условий (они же ключи в формировании правил)
   * @return array|string
   */
  public static function GetControlID()
  {
    return [
      'RemoteRegion'
    ];
  }

  /**
   * Описание и сортировка
   * @return array
   */
  public static function GetControlDescr()
  {
    $description = parent::GetControlDescr();
    $description['SORT'] = 1;
    return $description;
  }

  /**
   * Формирование данных для визуального представления условия
   * @param $arControls
   * @return array
   */
  public static function GetShowIn($arControls)
  {
    if (!is_array($arControls))
      $arControls = array($arControls);
    return array_values(array_unique($arControls));
  }

  /**
   * Добавление пункта в список условий с указанием отдельной группы
   * @param $arParams
   * @return array
   */
  public static function GetControlShow($arParams)
  {
    $arControls = static::GetControls();
    $arResult = array(
      'controlgroup' => true,
      'group' =>  true,
      'label' => 'Регионы',
      'showIn' => static::GetShowIn($arParams['SHOW_IN_GROUPS']),
      'children' => array()
    );
    foreach ($arControls as &$arOneControl) {
      $arResult['children'][] = array(
        'controlId' => $arOneControl['ID'],
        'group' => false,
        'label' => $arOneControl['LABEL'],
        'showIn' => static::GetShowIn($arParams['SHOW_IN_GROUPS']),
        'control' => array(
          $arOneControl['PREFIX'],
          static::GetLogicAtom($arOneControl['LOGIC']),
          static::GetValueAtom($arOneControl['JS_VALUE'])
        )
      );
    }
    if (isset($arOneControl))
      unset($arOneControl);

    return $arResult;
  }

  /**
   * Формирование данных для визуального представления условия
   * @param bool $controlId
   * @return array|bool|mixed
   * @throws \Bitrix\Main\ArgumentException
   */
  public static function GetControls($controlId = false)
  {
    // формируем правила
    $controlList = array(
      'RemoteRegion' => array(
        'ID' => 'RemoteRegion',
        'FIELD' => 'REMOTE_REGION',
        'FIELD_TYPE' => 'int',
        'MULTIPLE' => 'N',
        'GROUP' => 'N',
        'LABEL' => 'Тип региона',
        'PREFIX' => 'Поле Тип региона',
        'LOGIC' => static::getLogic(array(BT_COND_LOGIC_EQ, BT_COND_LOGIC_NOT_EQ)),
        'JS_VALUE' => array(
          'type' => 'select',
          'values' => static::getVariaties(),
          'multiple' => 'N',
        ),
        'PHP_VALUE' => ''
      )
    );

    foreach ($controlList as &$control) {
      if (!isset($control['PARENT']))
        $control['PARENT'] = true;

      $control['MULTIPLE'] = 'N';
      $control['GROUP'] = 'N';
    }
    unset($control);

    if (false === $controlId) {
      return $controlList;
    } elseif (isset($controlList[$controlId])) {
      return $controlList[$controlId];
    } else {
      return false;
    }
  }

  /**
   * Обработка логики правил.
   * Функция должна вернуть колбэк того, что должно быть выполнено при наступлении условий
   * @param $arOneCondition
   * @param $arParams
   * @param $arControl
   * @param bool $arSubs
   * @return bool|mixed|string
   */
  public static function Generate($arOneCondition, $arParams, $arControl, $arSubs = false)
  {
    return __CLASS__ . '::checkRegion(' . $arParams["ORDER"] . ', ' .
      '"' . $arOneCondition['logic'] . '", ' .
      '"' . $arOneCondition['value'] . '")';
  }

  /**
   * Логика кастомного условия
   * @param $row
   * @param $value
   * @return bool
   */
  public static function checkRegion($order, $logic, $value)
  {
    if ($order === null) {
      return true;
    }

    $isEqual = $logic === 'Equal';

    $property = OrderPropsTable::getList([
      'filter' => ['CODE' => 'DELIVERY_REMOTE_REGION'],
      'select' => ['ID', 'NAME', 'CODE']
    ])->fetch();

    $isFar = $order['ORDER_PROP'][$property['ID']] == 'Y';
    $compareIsFar = $value == "2";

    return $isEqual
      ? $isFar === $compareIsFar
      : $isFar !== $compareIsFar;
  }

  /**
   * Получаем список значений
   * @return array
   * @throws \Bitrix\Main\ArgumentException
   */
  protected static function getVariaties()
  {
    $variaties = [
      1 => 'близкий',
      2 => 'дальний'
    ];

    return $variaties;
  }
}
