<?php

declare(strict_types=1);

class MQTTSyncServer extends IPSModule
{
    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyString('GroupTopic', 'symcon/group');
        $this->RegisterPropertyBoolean('Retain', false);
        $this->RegisterPropertyString('Devices', '[]');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $group = $this->ReadPropertyString('GroupTopic');
        if ($group === '') {
            $this->SetReceiveDataFilter('^$');
        } else {
            $this->SetReceiveDataFilter('.*' . preg_quote($group, '/') . '.*');
        }
    }

    public function ReceiveData($JSONString)
    {
        $this->SendDebug('ReceiveData', $JSONString, 0);
        $data = json_decode($JSONString);
        if (!is_object($data) || !isset($data->Topic) || !isset($data->Payload)) {
            $this->SendDebug('ReceiveData', 'Invalid data structure', 0);
            return;
        }

        $payload = json_decode($data->Payload, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->SendDebug('ReceiveData', 'Invalid payload JSON', 0);
            return;
        }
    }

    protected function SendMQTT(string $topic, string $payload, bool $retain = false)
    {
        $Data = [
            'DataID' => '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}',
            'PacketType' => 3,
            'QualityOfService' => 0,
            'Retain' => $retain,
            'Topic' => $topic,
            'Payload' => $payload
        ];
        $DataJSON = json_encode($Data);
        $this->SendDebug('SendMQTT', $DataJSON, 0);
        $this->SendDataToParent($DataJSON);
    }

    public function sendConfigurationToClient()
    {
        $this->SendDebug('sendConfigurationToClient', 'Start', 0);

        $devices = json_decode($this->ReadPropertyString('Devices'), true);
        $retain = $this->ReadPropertyBoolean('Retain');

        foreach ($devices as $device) {
            if (!isset($device['MQTTTopic'])) {
                continue;
            }

            $payload = json_encode([
                'name' => $device['Name'] ?? '',
                'location' => $device['Location'] ?? '',
                'area' => $device['Area'] ?? 0,
                'description' => $device['Description'] ?? '',
                'type' => $device['Type'] ?? 'sensor'
            ]);

            $this->SendMQTT($device['MQTTTopic'], $payload, $retain);
        }

        $this->SendDebug('sendConfigurationToClient', 'Done', 0);
    }

    public function sendProfilesToClient()
    {
        $this->SendDebug('sendProfilesToClient', 'Start', 0);
        // Implement profile sending logic if needed
        $this->SendDebug('sendProfilesToClient', 'Done', 0);
    }

    public function sendVariablesToClient()
    {
        $this->SendDebug('sendVariablesToClient', 'Start', 0);

        $devices = json_decode($this->ReadPropertyString('Devices'), true);
        $retain = $this->ReadPropertyBoolean('Retain');

        foreach ($devices as $device) {
            if (!isset($device['MQTTTopic']) || !isset($device['ObjectID'])) {
                continue;
            }

            $objectId = (int)$device['ObjectID'];
            if (!IPS_VariableExists($objectId)) {
                continue;
            }

            $value = GetValue($objectId);
            $valueJSON = json_encode($value);
            $this->SendMQTT($device['MQTTTopic'], $valueJSON, $retain);
        }

        $this->SendDebug('sendVariablesToClient', 'Done', 0);
    }

    public function synchronizeData()
    {
        $this->SendDebug('synchronizeData', 'Start', 0);
        $this->sendConfigurationToClient();
        $this->sendProfilesToClient();
        $this->sendVariablesToClient();
        $this->SendDebug('synchronizeData', 'Done', 0);
    }
}
