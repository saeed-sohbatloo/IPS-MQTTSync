<?php

declare(strict_types=1);

// MQTTSyncClientDevice: Represents a single MQTT-enabled device in IP-Symcon
class MQTTSyncClientDevice extends IPSModule
{
    public function Create()
    {
        parent::Create();

        // Base device info
        $this->RegisterPropertyString('MQTTTopic', '');
        $this->RegisterPropertyString('GroupTopic', '');
        $this->RegisterPropertyString('Location', '');
        $this->RegisterPropertyInteger('Area', 0);
        $this->RegisterPropertyString('Description', '');
        $this->RegisterPropertyString('InstallationDate', '');
        $this->RegisterPropertyBoolean('IsActive', true);

        // Variable list to monitor
        $this->RegisterPropertyString('MonitoredVariables', '[]');

        // Timer for sending
        $this->RegisterTimer('SendDeviceDataTimer', 0, 'MQTTSYNC_SendDeviceInfoMQTT($InstanceID);');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $group = $this->ReadPropertyString('GroupTopic');
        $topic = $this->ReadPropertyString('MQTTTopic');
        $this->SetReceiveDataFilter('.*mqttsync/' . $group . '/' . $topic . '.*');

        // Set timer interval to 10 seconds
        $this->SetTimerInterval('SendDeviceDataTimer', 10000);
    }

    public function ReceiveData($JSONString)
    {
        $this->SendDebug('ReceiveData', $JSONString, 0);
        $data = json_decode($JSONString);
        if (!isset($data->Topic) || !isset($data->Payload)) {
            $this->SendDebug('ReceiveData', 'Invalid data structure', 0);
            return;
        }

        $payload = json_decode($data->Payload, true);
        if (is_array($payload)) {
            foreach ($payload as $key => $value) {
                $this->SendDebug('Payload', $key . ': ' . print_r($value, true), 0);
            }
        }
    }

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

        // Add selected variables to payload
        $variables = json_decode($this->ReadPropertyString('MonitoredVariables'), true);
        $values = [];
        if (is_array($variables)) {
            foreach ($variables as $entry) {
                if (isset($entry['VariableID']) && IPS_VariableExists($entry['VariableID'])) {
                    $values[IPS_GetName($entry['VariableID'])] = GetValue($entry['VariableID']);
                }
            }
        }

        $payload['Values'] = $values;

        $topic = 'mqttsync/' . $payload['GroupTopic'] . '/' . $payload['MQTTTopic'] . '/info';
        $this->SendMQTT($topic, json_encode($payload));
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
}
