<?php

declare(strict_types=1);

// MQTTSyncClientConfigurator: Manages a group of MQTT devices in IP-Symcon
class MQTTSyncClientConfigurator extends IPSModule
{
    // Called once when the instance is created
    public function Create()
    {
        parent::Create();
        // Register group topic and devices list properties
        $this->RegisterPropertyString('GroupTopic', ''); // MQTT group topic for devices
        $this->RegisterPropertyString('Devices', '[]');  // JSON encoded array of devices configuration
    }

    // Called whenever properties are changed or instance is loaded
    public function ApplyChanges()
    {
        parent::ApplyChanges();
        // Read group topic to set MQTT receive filter accordingly
        $group = $this->ReadPropertyString('GroupTopic');
        // Set filter to only receive messages that match the group topic pattern
        $this->SetReceiveDataFilter('.*mqttsync/' . $group . '.*');
    }

    // Handles incoming MQTT data
    public function ReceiveData(string $JSONString)
    {
        $this->SendDebug('ReceiveData', $JSONString, 0);
        $data = json_decode($JSONString);
        // Validate the received data structure
        if (!isset($data->Topic) || !isset($data->Payload)) {
            $this->SendDebug('ReceiveData', 'Invalid data structure', 0);
            return;
        }
        // Decode the payload JSON into array
        $payload = json_decode($data->Payload, true);
        // If payload is an array, log all key-value pairs for debugging
        if (is_array($payload)) {
            foreach ($payload as $key => $value) {
                $this->SendDebug('Payload', $key . ': ' . print_r($value, true), 0);
            }
        }
    }

    // Updates the stored device list buffer with given array of devices
    protected function UpdateDeviceList(array $devices)
    {
        // Store the devices JSON string in module buffer for later use
        $this->SetBuffer('Devices', json_encode($devices));
    }
}
