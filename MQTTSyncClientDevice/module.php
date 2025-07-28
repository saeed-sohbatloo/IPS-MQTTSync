<?php

declare(strict_types=1);

// MQTTSyncClientDevice: Represents a single MQTT-enabled device in IP-Symcon
class MQTTSyncClientDevice extends IPSModule
{
    // Called once when the instance is created
    public function Create()
    {
        parent::Create();
        // Register all device properties
        $this->RegisterPropertyString('MQTTTopic', ''); // Unique topic for this device
        $this->RegisterPropertyString('GroupTopic', ''); // Group topic for device grouping
        $this->RegisterPropertyString('Location', ''); // Physical location of the device
        $this->RegisterPropertyInteger('Area', 0); // Area in square meters
        $this->RegisterPropertyString('Description', ''); // Additional notes
        $this->RegisterPropertyString('InstallationDate', ''); // Installation date (string)
        $this->RegisterPropertyBoolean('IsActive', true); // Device active status

        // New: Register property for send interval in seconds (default 10)
        $this->RegisterPropertyInteger('SendInterval', 10);

        // Register a timer with a unique ident to control periodic sending
        $this->RegisterTimer('SendTimer', 0, '');  // 0 means timer off initially
    }

    // Called whenever properties are changed or instance is loaded
    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // Set up MQTT receive filter for this device
        $group = $this->ReadPropertyString('GroupTopic');
        $topic = $this->ReadPropertyString('MQTTTopic');
        $this->SetReceiveDataFilter('.*mqttsync/' . preg_quote($group, '/') . '/' . preg_quote($topic, '/') . '.*');

        // Read send interval from properties
        $interval = $this->ReadPropertyInteger('SendInterval');
        if ($interval < 1) {
            $interval = 10; // fallback to 10 seconds if invalid
        }

        // Set or disable timer
        if ($this->ReadPropertyBoolean('IsActive')) {
            // Activate timer with interval in milliseconds
            $this->SetTimerInterval('SendTimer', $interval * 1000);
            // Set timer script to call our sending method
            $this->SetTimerScript('SendTimer', 'MQTTSYNC_SendDeviceInfoMQTT(' . $this->InstanceID . ');');
        } else {
            // Deactivate timer if inactive
            $this->SetTimerInterval('SendTimer', 0);
        }
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

    // Public method to be called by timer (needs to be public static or public instance method)
    public function SendDeviceInfoMQTT()
    {
        $payload = [
            'MQTTTopic' => $this->ReadPropertyString('MQTTTopic'),
            'GroupTopic' => $this->ReadPropertyString('GroupTopic'),
            'Location' => $this->ReadPropertyString('Location'),
            'Area' => $this->ReadPropertyInteger('Area'),
            'Description' => $this->ReadPropertyString('Description'),
            'InstallationDate' => $this->ReadPropertyString('InstallationDate'),
            'IsActive' => $this->ReadPropertyBoolean('IsActive')
        ];
        $topic = 'mqttsync/' . $payload['GroupTopic'] . '/' . $payload['MQTTTopic'] . '/info';
        $this->SendMQTT($topic, json_encode($payload));
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
