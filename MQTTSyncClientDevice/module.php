<?php

declare(strict_types=1);

// MQTTSyncClientDevice: Represents a single MQTT-enabled device in IP-Symcon
class MQTTSyncClientDevice extends IPSModule
{
    public function Create()
    {
        parent::Create();

        // Device Properties
        $this->RegisterPropertyString('MQTTTopic', '');
        $this->RegisterPropertyString('GroupTopic', '');
        $this->RegisterPropertyString('Location', '');
        $this->RegisterPropertyInteger('Area', 0);
        $this->RegisterPropertyString('Description', '');
        $this->RegisterPropertyString('InstallationDate', '');
        $this->RegisterPropertyBoolean('IsActive', true);

        // Monitoring
        $this->RegisterPropertyString('MonitoredVariables', '[]');
        $this->RegisterPropertyInteger('UpdateInterval', 10);

        // Timer
        $this->RegisterTimer('SendMQTTTimer', 0, 'MQTTSYNC_SendMonitoredData($_IPS["TARGET"]);');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // MQTT Filter
        $group = $this->ReadPropertyString('GroupTopic');
        $topic = $this->ReadPropertyString('MQTTTopic');
        $this->SetReceiveDataFilter('.*mqttsync/' . $group . '/' . $topic . '.*');

        // Timer einstellen
        $interval = $this->ReadPropertyInteger('UpdateInterval');
        $this->SetTimerInterval('SendMQTTTimer', $interval * 1000);
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

    public function SendMonitoredData()
    {
        $variables = json_decode($this->ReadPropertyString('MonitoredVariables'), true);
        if (!is_array($variables)) {
            $this->SendDebug('MonitoredVariables', 'Invalid variable list', 0);
            return;
        }

        $values = [];
        foreach ($variables as $var) {
            if (!isset($var['VariableID'])) {
                continue;
            }
            $varID = (int)$var['VariableID'];
            if (!IPS_VariableExists($varID)) {
                continue;
            }
            $values[IPS_GetName($varID)] = GetValue($varID);
        }

        if (!empty($values)) {
            $topic = 'mqttsync/' . $this->ReadPropertyString('GroupTopic') . '/' . $this->ReadPropertyString('MQTTTopic') . '/values';
            $this->SendMQTT($topic, json_encode($values));
        }
    }

    protected function SendDeviceInfoMQTT()
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
