<?php

declare(strict_types=1);

class MQTTSyncServer extends IPSModule
{
    // Called once when the instance is created
    public function Create()
    {
        parent::Create();
        // Register a string property to hold the MQTT group topic
        $this->RegisterPropertyString('GroupTopic', '');
        // Register a boolean property to indicate whether to retain MQTT messages
        $this->RegisterPropertyBoolean('Retain', false);
        // Register a string property to hold JSON array of device configurations
        $this->RegisterPropertyString('Devices', '[]');
    }

    // Called when properties change or instance is loaded
    public function ApplyChanges()
    {
        parent::ApplyChanges();
        // Read the GroupTopic property
        $group = $this->ReadPropertyString('GroupTopic');
        // Set the data filter to receive only MQTT topics under mqttsync/{group}
        $this->SetReceiveDataFilter('.*mqttsync/' . $group . '.*');
    }

    // Called when data is received from parent (MQTT messages)
    public function ReceiveData(string $JSONString)
    {
        $this->SendDebug('ReceiveData', $JSONString, 0);
        // Decode the JSON string into an object
        $data = json_decode($JSONString);
        // Validate the data structure
        if (!isset($data->Topic) || !isset($data->Payload)) {
            $this->SendDebug('ReceiveData', 'Invalid data structure', 0);
            return;
        }
        // Decode the payload JSON into associative array
        $payload = json_decode($data->Payload, true);
        // If payload is array, iterate and debug log each key/value
        if (is_array($payload)) {
            foreach ($payload as $key => $value) {
                $this->SendDebug('Payload', $key . ': ' . print_r($value, true), 0);
            }
        }
    }

    // Helper method to send MQTT messages via parent module
    protected function SendMQTT(string $topic, string $payload, bool $retain = false)
    {
        // Prepare the MQTT message data array
        $Data = [
            'DataID' => '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}', // MQTT parent GUID
            'PacketType' => 3, // Publish packet type
            'QualityOfService' => 0, // QoS 0 (at most once)
            'Retain' => $retain,
            'Topic' => $topic,
            'Payload' => $payload
        ];
        // Encode to JSON string
        $DataJSON = json_encode($Data);
        // Debug output of the data to be sent
        $this->SendDebug('SendMQTT', $DataJSON, 0);
        // Send data to the parent (MQTT instance)
        $this->SendDataToParent($DataJSON);
    }
}
