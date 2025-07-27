<?php

declare(strict_types=1);

// MQTTSyncServer: Handles MQTT server-side logic in IP-Symcon
class MQTTSyncServer extends IPSModule
{
    // Called once when the instance is created
    public function Create()
    {
        parent::Create();
        // Register properties for group topic, retain flag, and device list
        $this->RegisterPropertyString('GroupTopic', ''); // Group topic for all devices
        $this->RegisterPropertyBoolean('Retain', false); // MQTT retain flag
        $this->RegisterPropertyString('Devices', '[]'); // JSON array of device configs
    }

    // Called whenever properties are changed or instance is loaded
    public function ApplyChanges()
    {
        parent::ApplyChanges();
        // Set up MQTT receive filter for this group
        $group = $this->ReadPropertyString('GroupTopic');
        if ($group === '') {
            $this->SetReceiveDataFilter('^$'); // No filter if group is empty
        } else {
            $this->SetReceiveDataFilter('.*mqttsync/' . preg_quote($group, '/') . '.*'); // Filter for group topic
        }
    }

    // Handles incoming MQTT data
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
        // Add your custom processing here if needed
    }

    // Helper to send MQTT messages via parent
    protected function SendMQTT(string $topic, string $payload, bool $retain = false)
    {
        // Prepare data for IP-Symcon MQTT parent
        $Data = [
            'DataID' => '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}',
            'PacketType' => 3, // PUBLISH
            'QualityOfService' => 0,
            'Retain' => $retain,
            'Topic' => $topic,
            'Payload' => $payload
        ];
        $DataJSON = json_encode($Data);
        $this->SendDebug('SendMQTT', $DataJSON, 0);
        $this->SendDataToParent($DataJSON);
    }
}
