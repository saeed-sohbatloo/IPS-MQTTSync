<?php

declare(strict_types=1);

class MQTTTest extends IPSModule
{
    public function Create()
    {
        // Never delete this line!
        parent::Create();

        // Parent connection
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');

        // Register properties matching the form
        $this->RegisterPropertyString('GroupTopic', 'symcon/stm');
        $this->RegisterPropertyBoolean('Retain', false);
        $this->RegisterPropertyString('Devices', '[]');
    }

    public function ApplyChanges()
    {
        // Never delete this line!
        parent::ApplyChanges();

        $DevicesJSON = $this->ReadPropertyString('Devices');
        $Devices = json_decode($DevicesJSON);
        if (!is_array($Devices)) {
            return; // در صورتی که JSON معتبر نباشد خارج می‌شویم
        }

        foreach ($Devices as $Device) {
            // فقط دستگاه‌های فعال
            if (!property_exists($Device, 'Active') || $Device->Active === false) {
                continue;
            }

            if (!property_exists($Device, 'ObjectID') || !IPS_ObjectExists($Device->ObjectID)) {
                continue;
            }

            // بررسی و ثبت پروفایل
            $profileName = 'MQTT_' . $Device->ObjectID;
            if (!IPS_VariableProfileExists($profileName)) {
                IPS_CreateVariableProfile($profileName, 1);
                IPS_SetVariableProfileIcon($profileName, 'Network');
            }

            if (IPS_GetVariableProfile($profileName)['ProfileType'] !== 1) {
                throw new Exception('Variable profile type mismatch');
            }

            $type = $Device->Type ?? 'sensor';

            switch ($type) {
                case 'sensor':
                    IPS_SetVariableProfileText($profileName, ' ', ' Unit');
                    break;
                case 'actuator':
                    IPS_SetVariableProfileText($profileName, 'Off', 'On');
                    break;
                case 'other':
                default:
                    IPS_SetVariableProfileText($profileName, '', '');
                    break;
            }
        }
    }

    public function ReceiveData($JSONString)
    {
        $Data = json_decode($JSONString, true);
        if ($Data === null) {
            return;
        }

        if (!isset($Data['Command'])) {
            return;
        }

        switch ($Data['Command']) {
            case 'SetValue':
                if (isset($Data['ObjectID']) && isset($Data['Value'])) {
                    $objectID = (int)$Data['ObjectID'];
                    $value = $Data['Value'];
                    if (IPS_VariableExists($objectID)) {
                        SetValue($objectID, $value);
                    }
                }
                break;
        }
    }

    public function SendMQTTData(string $Command, string $Payload)
    {
        $MQTTTopic = $this->ReadPropertyString('GroupTopic');
        $Retain = $this->ReadPropertyBoolean('Retain');

        $data = [
            'DataID' => '{46A53B59-99EF-4A15-B6D2-C2D8DBD92F76}',
            'MQTTTopic' => $MQTTTopic . '/' . $Command,
            'Payload' => $Payload,
            'Retain' => $Retain
        ];

        $this->SendDataToParent(json_encode($data));
    }

    // ارسال پیکربندی دستگاه‌ها
    public function sendConfiguration()
    {
        $DevicesJSON = $this->ReadPropertyString('Devices');
        $Devices = json_decode($DevicesJSON);
        if (!is_array($Devices)) {
            return;
        }

        $Configuration = [];
        foreach ($Devices as $Device) {
            if (!property_exists($Device, 'Active') || $Device->Active === false) {
                continue;
            }
            if (!property_exists($Device, 'ObjectID') || !IPS_ObjectExists($Device->ObjectID)) {
                continue;
            }
            $tmpConfiguration = [];
            $tmpConfiguration['ObjectID'] = $Device->ObjectID;
            $tmpConfiguration['ObjectName'] = IPS_GetObject($Device->ObjectID)['ObjectName'];
            $tmpConfiguration['MQTTTopic'] = $Device->MQTTTopic;
            $tmpConfiguration['Name'] = $Device->Name ?? '';
            $tmpConfiguration['Type'] = $Device->Type ?? '';
            $tmpConfiguration['ObjectType'] = IPS_GetObject($Device->ObjectID)['ObjectType'];
            $Configuration[] = $tmpConfiguration;
        }
        $this->SendMQTTData('Configuration', json_encode($Configuration));
    }

    // ارسال پروفایل‌های متغیرها
    public function sendVariablenProfiles()
    {
        // کد نمونه برای ارسال پروفایل‌ها به کلاینت
        // این بخش باید بر اساس نیازهای دقیق شما توسعه داده شود
        $Profiles = []; // فرضا آرایه پروفایل‌ها
        $this->SendMQTTData('VariableProfiles', json_encode($Profiles));
    }

    // ارسال متغیرها
    public function sendVariablen()
    {
        // کد نمونه برای ارسال مقادیر متغیرها
        $Variables = [];
        $DevicesJSON = $this->ReadPropertyString('Devices');
        $Devices = json_decode($DevicesJSON);
        if (!is_array($Devices)) {
            return;
        }

        foreach ($Devices as $Device) {
            if (!property_exists($Device, 'Active') || $Device->Active === false) {
                continue;
            }
            if (!property_exists($Device, 'ObjectID') || !IPS_ObjectExists($Device->ObjectID)) {
                continue;
            }
            $objectID = $Device->ObjectID;
            $Variables[$objectID] = GetValue($objectID);
        }
        $this->SendMQTTData('Variables', json_encode($Variables));
    }

    // توابع مربوط به دکمه‌های فرم

    public function synchronizeData()
    {
        $this->sendConfiguration();
        $this->sendVariablenProfiles();
        $this->sendVariablen();
    }

    public function sendConfigurationToClient()
    {
        $this->sendConfiguration();
    }

    public function sendProfilesToClient()
    {
        $this->sendVariablenProfiles();
    }

    public function sendVariablesToClient()
    {
        $this->sendVariablen();
    }
}
