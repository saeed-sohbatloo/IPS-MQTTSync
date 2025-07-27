<?php

declare(strict_types=1);

// MQTTSyncClientConfigurator: Manages a group of MQTT devices in IP-Symcon
class MQTTSyncClientConfigurator extends IPSModule
{
    // Called once when the instance is created
    public function Create()
    {
        parent::Create();
        // Register properties for group topic and device list
        $this->RegisterPropertyString('GroupTopic', ''); // Group topic for all devices
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

    // Example method to update device list (extend as needed)
    protected function UpdateDeviceList(array $devices)
    {
        $this->SetBuffer('Devices', json_encode($devices));
    }
}
