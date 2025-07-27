<?php

declare(strict_types=1);

// MQTTSyncClientDevice: Represents a single MQTT-enabled device in IP-Symcon
class MQTTSyncClientDevice extends IPSModule
{
    // Called once when the instance is created
    public function Create()
    {
        parent::Create();
        // Register device-specific properties
        $this->RegisterPropertyString('MQTTTopic', '');     // Unique topic for this device
        $this->RegisterPropertyString('GroupTopic', '');    // Group topic for device grouping
        $this->RegisterPropertyString('Location', '');      // Physical location of the device
        $this->RegisterPropertyInteger('Area', 0);          // Area in square meters
        $this->RegisterPropertyString('Description', '');   // Additional notes or description
        $this->RegisterPropertyString('InstallationDate', ''); // Installation date as string
        $this->RegisterPropertyBoolean('IsActive', true);   // Device active status flag
    }

    // Called whenever properties change or instance is loaded
    public function ApplyChanges()
    {
        parent::ApplyChanges();
        // Read properties to build the MQTT receive filter
        $group = $this->ReadPropertyString('GroupTopic');
        $topic = $this->ReadPropertyString('MQTTTopic');
        // Set data filter to only receive messages related to this device's MQTT topic
        $this->SetReceiveDataFilter('.*mqttsync/' . $group . '/' . $topic . '.*');
    }

    // Handles incoming MQTT data
    public function ReceiveData(string $JSONString)
    {
        $this->SendDebug('ReceiveData', $JSONString, 0);
        $data = json_decode($JSONString);
        // Validate payload structure
        if (!isset($data->Topic) || !isset($data->Payload)) {
            $this->SendDebug('ReceiveData', 'Invalid data structure', 0);
            return;
        }
        // Decode the JSON payload into an associative array
        $payload = json_decode($data->Payload, true);
        // If payload is an array, log all key-value pairs for debugging
        if (is_array($payload)) {
            foreach ($payload as $key => $value) {
                $this->SendDebug('Payload', $key . ': ' . print_r($value, true), 0);
            }
        }
    }

    // Sends all device information as an MQTT message
    protected function SendDeviceInfoMQTT()
    {
        // Compose payload from the device properties
        $payload = [
            'MQTTTopic' => $this->ReadPropertyString('MQTTTopic'),
            'GroupTopic' => $this->ReadPropertyString('GroupTopic'),
            'Location' => $this->ReadPropertyString('Location'),
            'Area' => $this->ReadPropertyInteger('Area'),
            'Description' => $this->ReadPropertyString('Description'),
            'InstallationDate' => $this->ReadPropertyString('InstallationDate'),
            'IsActive' => $this->ReadPropertyBoolean('IsActive')
        ];
        // Construct the MQTT topic for sending device info
        $topic = 'mqttsync/' . $payload['GroupTopic'] . '/' . $payload['MQTTTopic'] . '/info';
        // Send MQTT message with the device info JSON payload
        $this->SendMQTT($topic, json_encode($payload));
    }

    // Helper method to send MQTT messages via the parent module
    protected function SendMQTT(string $topic, string $payload, bool $retain = false)
    {
        $Data = [
            'DataID' => '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}', // MQTT parent GUID
            'PacketType' => 3, // Publish packet type
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
