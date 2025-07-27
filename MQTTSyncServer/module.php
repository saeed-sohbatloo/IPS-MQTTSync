<?php

declare(strict_types=1);

// MQTTSyncServer: Handles MQTT server-side logic in IP-Symcon
class MQTTSyncServer extends IPSModule
{
    // Constructor: Always receives $InstanceID in IP-Symcon
    public function __construct($InstanceID)
    {
        // Call parent constructor with InstanceID
        parent::__construct($InstanceID);
    }

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
        $this->SetReceiveDataFilter('.*mqttsync/' . $group . '.*');
    }

    // Handles incoming MQTT data
    public function ReceiveData($JSONString)
    {
        $this->SendDebug('ReceiveData', $JSONString, 0);
        $data = json_decode($JSONString);
        if (!isset($data->Topic) || !isset($data->Payload)) {
            $this->SendDebug('ReceiveData', 'Invalid data structure', 0);
            return;
        }
        // Example: handle incoming payload (extend as needed)
        $payload = json_decode($data->Payload, true);
        if (is_array($payload)) {
            // Example: log received values
            foreach ($payload as $key => $value) {
                $this->SendDebug('Payload', $key . ': ' . print_r($value, true), 0);
            }
        }
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
