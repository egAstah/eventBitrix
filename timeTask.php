<?php
CModule::IncludeModule("calendar");
CModule::IncludeModule("task");
CModule::IncludeModule('im');

AddEventHandler("tasks", "OnTaskElapsedTimeAdd", array("AddTimeTask", "OnTaskElapsedTimeAddHandler"));

function weekend($date)
{
    $result = [];
    for ($i = 1; $i <= date("t", strtotime($date . '-01')); $i++) {
        $weekend = date("w", strtotime($date . '-' . $i));
        if ($weekend == 0 || $weekend == 6) {
            if ($i < 10) {
                $result[] = '0' . $i . '.' . date('m.Y', strtotime($date));
            } else {
                $result[] = $i . '.' . date('m.Y', strtotime($date));
            }
        }
    }
    return $result;
}

function weekendArr()
{
    $weekendArrOne = [];
    for ($i = 1; $i <= 12; $i++) {
        if ($i < 10) {
            $weekendArrOne[] = weekend(date('Y') . '-0' . $i);
        } else {
            $weekendArrOne[] = weekend(date('Y') . '-' . $i);
        }
    }
    $result = [];
    foreach ($weekendArrOne as $item) {
        foreach ($item as $dateWeek) {
            $result[] = $dateWeek;
        }
    }

    return $result;
}

function getHolidays()
{
    $holidays = explode(',', CCalendar::GetSettings()['year_holidays']);
    foreach ($holidays as $k => $item) {
        $holidays[$k] = date('d.m.Y', strtotime($item . '.' . date('Y')));
    }

    $arSelect = ["ID", "IBLOCK_ID", "PROPERTY_*"];
    $arFilter = array("IBLOCK_ID" => 199);
    $res = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
    while ($ob = $res->GetNextElement()) {
        $arProps = $ob->GetProperties();
        foreach ($arProps['DATE']['VALUE'] as $item) {
            $holidays[] = $item;
        }
    }
//    $holidays = array_merge($holidays, weekendArr());

    return $holidays;
}

class AddTimeTask
{
    static function OnTaskElapsedTimeAddHandler($ID, &$arFields)
    {
        $arFieldsUpdate = ['MINUTES' => 0];
        $obElapsed = new CTaskElapsedTime();
        $rsTask = CTasks::GetByID($arFields['TASK_ID']);
        if ($arTask = $rsTask->GetNext()) {
            $arFieldsNotify = array(
                "NOTIFY_TITLE" => 'Время в задаче ' . $arTask['TITLE'] . ' не учтено!',
                "MESSAGE" => 'В <a href="https://' . $_SERVER['SERVER_NAME'] . '/company/personal/user/' . $arFields['USER_ID'] . '/tasks/task/view/' . $arFields['TASK_ID'] . '/">задаче</a> не было добавлено время, поскольку вы пытались списать время за закрытый период!',
                "MESSAGE_TYPE" => IM_MESSAGE_SYSTEM,
                "TO_USER_ID" => $arFields['USER_ID'],
                "NOTIFY_TYPE" => IM_NOTIFY_SYSTEM,
                "NOTIFY_MODULE" => "main",
                "NOTIFY_EVENT" => "manage",
            );
        }

        $taskTimeList = CTaskElapsedTime::GetList(
            [],
            ['TASK_ID' => $arFields['TASK_ID'], 'ID' => $ID]
        );

        $error = false;

        while ($arTaskTimeList = $taskTimeList->Fetch()) {
            $createDate = date('d.m.Y', strtotime($arTaskTimeList['CREATED_DATE']));
            $nowDate = date('d.m.Y');
            $createMonth = date('m', strtotime($createDate));
            $nowMonth = date('m', strtotime($nowDate));
            $createYear = date('Y', strtotime($createDate));
            $nowYear = date('Y', strtotime($nowDate));

            # Нельзя списать за прошлый год
            if ($createYear < $nowYear) {
                $error = true;
            }

            # Нельзя списать за прошлый месяц, можно списать за след месяц и год
            if ($createYear <= $nowYear) {
                if ($createMonth < $nowMonth) {
                    $error = true;
                }
            }

            # Нельзя списать если текущая дата не праздничный день или первый день месяца
            if (array_search($nowDate, getHolidays()) != '') {
                $createMonthInt = (int)$createMonth;
                $nowMonthInt = (int)$nowMonth;
                $sumMonth = $nowMonthInt - $createMonthInt;
                if ($sumMonth <= 1 && $sumMonth >= 0) {
                    $error = false;
                } else {
                    $error = true;
                }
            }

            if ($error) {
                CIMMessenger::Add($arFieldsNotify);
                $obElapsed->Update($ID, $arFieldsUpdate);
            }
        }
    }
}
?>
