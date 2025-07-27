<?php

declare(strict_types=1);

class MQTTSyncClientDevice extends IPSModule
{
    public function Create()
    {
        parent::Create();
        // Properties
        $this->RegisterPropertyString('MQTTTopic', '');
        $this->RegisterPropertyString('GroupTopic', '');
        $this->RegisterPropertyString('VariablesToSend', '[]'); // JSON list of variables to send

        // Register timer with 0 interval (disabled by default)
        $this->RegisterTimer('SendVariablesTimer', 0, 'MQTTSYNC_SendVariables($_IPS[\'TARGET\']);');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // Set receive filter (optional, adjust if needed)
        $group = $this->ReadPropertyString('GroupTopic');
        $topic = $this->ReadPropertyString('MQTTTopic');
        $this->SetReceiveDataFilter('.*mqttsync/' . preg_quote($group, '/') . '/' . preg_quote($topic, '/') . '.*');

        // Read variables to send
        $vars = json_decode($this->ReadPropertyString('VariablesToSend'), true);

        if (is_array($vars) && count($vars) > 0) {
            // Enable timer every 10 seconds
            $this->SetTimerInterval('SendVariablesTimer', 10000);
        } else {
            // Disable timer if no variables
            $this->SetTimerInterval('SendVariablesTimer', 0);
        }
    }

    // This method is called by the timer
    public function SendVariables()
    {
        $vars = json_decode($this->ReadPropertyString('VariablesToSend'), true);
        if (!is_array($vars)) {
            return;
        }

        foreach ($vars as $var) {
            if (!isset($var['VariableID']) || !IPS_VariableExists($var['VariableID'])) {
                continue;
            }
            $value = GetValue($var['VariableID']);
            $topicSuffix = $var['TopicSuffix'] ?? 'value';

            $topic = 'mqttsync/' . $this->ReadPropertyString('GroupTopic') . '/' . $this->ReadPropertyString('MQTTTopic') . '/' . $topicSuffix;

            $payload = json_encode([
                'value' => $value,
                'timestamp' => time()
            ]);

            $this->SendMQTT($topic, $payload);
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
}
