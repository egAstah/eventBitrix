<?php

use Bitrix\Crm\Service;
use Bitrix\Crm\Service\Container;

AddEventHandler("crm", "OnBeforeCrmDealUpdate", "beforeDealUpdateAct");
AddEventHandler("crm", "onCrmDynamicItemAdd_1036", "addSmartProcessAct");
AddEventHandler("crm", "onCrmDynamicItemAdd_1032", "setTitleSmartProcessAct");
AddEventHandler("crm", "onCrmDynamicItemUpdate_1032", "updateSmartProcessAct");
AddEventHandler("crm", "onCrmDynamicItemUpdate_1036", "updateSmartProcessDocs");

function beforeDealUpdateAct($arFields)
{
    $factory = Service\Container::getInstance()->getFactory(1036);

    $arFilter = ["ID" => $arFields['ID'], "CHECK_PERMISSIONS" => "N"];
    $arSelect = ['ID', 'COMPANY_ID'];
    $res = CCrmDeal::GetList(array(), $arFilter, $arSelect);

    if ($row = $res->Fetch()) {
        // Ссылка на счет
        if (isset($arFields['UF_CRM_1718021870680'])) {
            $data = [
                'id' => $row['COMPANY_ID'],
                'fields' => [
                    'UF_CONTRACT_PAY_LINK' => $arFields['UF_CRM_1718021870680'],
                ]
            ];
            $result = requestToBitrix('organizationSetFields', $data);
        }

        // Активный организатор
        if (isset($arFields['UF_CRM_1722504256010'])) {
            if ($arFields['UF_CRM_1722504256010'] == '226') $arFields['UF_CRM_1722504256010'] = 1;
            else $arFields['UF_CRM_1722504256010'] = 0;
            $data = [
                'id' => $row['COMPANY_ID'],
                'fields' => [
                    'UF_ACTIVE' => $arFields['UF_CRM_1722504256010'],
                ]
            ];
            $result = requestToBitrix('organizationSetFields', $data);
        }

        //Лицензионное вознаграждение (%)
        if (isset($arFields['UF_CRM_1733209646'])) {
            $data = [
                'id' => $row['COMPANY_ID'],
                'fields' => [
                    'UF_COOPERATION_PERCENT' => $arFields['UF_CRM_1733209646'],
                    'UF_CONTRACT_DATE' => date('d.m.Y H:i:s')
                ]
            ];
            $result = requestToBitrix('organizationSetFields', $data);
        }

        //Сервисный сбор (%)
        if (isset($arFields['UF_CRM_1733209676'])) {
            $data = [
                'id' => $row['COMPANY_ID'],
                'fields' => [
                    'UF_COOPERATION_SERVICE_FEE' => $arFields['UF_CRM_1733209676'],
                    'UF_CONTRACT_DATE' => date('d.m.Y H:i:s')
                ]
            ];
            $result = requestToBitrix('organizationSetFields', $data);
        }

        if (isset($arFields['STAGE_ID'])) {
            if ($arFields['STAGE_ID'] == 'C30:WON') {
                $data = [
                    'id' => $row['COMPANY_ID'],
                ];
                $result = requestToBitrix('setContractFinishStatus', $data);
            }
        }
    }
}
?>
