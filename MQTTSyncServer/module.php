<?php

declare(strict_types=1);

// MQTTSyncServer: Handles MQTT server-side logic in IP-Symcon
class MQTTSyncServer extends IPSModule
{
    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyString('GroupTopic', '');
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
            $this->SetReceiveDataFilter('.*mqttsync/' . preg_quote($group, '/') . '.*');
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

    // ✅ desired form format
    public function sendConfigurationToClient()
    {
        $this->SendDebug('sendConfigurationToClient', 'Configuration sending started', 0);

        $devices = json_decode($this->ReadPropertyString('Devices'), true);
        $groupTopic = $this->ReadPropertyString('GroupTopic');
        $retain = $this->ReadPropertyBoolean('Retain');

        foreach ($devices as $device) {
            $payload = json_encode([
                'name' => $device['Name'] ?? '',
                'location' => $device['Location'] ?? '',
                'area' => $device['Area'] ?? 0,
                'description' => $device['Description'] ?? '',
                'type' => $device['Type'] ?? 'sensor'
            ]);
            $topic = $device['MQTTTopic'] ?? '';
            if ($topic !== '') {
                $this->SendMQTT($topic, $payload, $retain);
            }
        }

        $this->SendDebug('sendConfigurationToClient', 'Configuration sending finished', 0);
    }
}
