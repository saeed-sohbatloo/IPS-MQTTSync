<?php

declare(strict_types=1);

class MQTTSyncServer extends IPSModule
{
    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyString('GroupTopic', 'symcon/group');
        $this->RegisterPropertyBoolean('Retain', false);
        $this->RegisterPropertyString('Devices', '[]');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $group = $this->ReadPropertyString('GroupTopic');
        if ($group === '') {
            $this->SetReceiveDataFilter('^$');
        } else {
            $this->SetReceiveDataFilter('.*' . preg_quote($group, '/') . '.*');
        }
    }

    public function ReceiveData($JSONString)
    {
        $this->SendDebug('ReceiveData', $JSONString, 0);
        $data = json_decode($JSONString);
        if (!isset($data->Topic) || !isset($data->Payload)) {
            $this->SendDebug('ReceiveData', 'Invalid structure', 0);
            return;
        }

        $topic = $data->Topic;
        $payloadRaw = $data->Payload;
        $payload = json_decode($payloadRaw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->SendDebug('Payload Error', json_last_error_msg(), 0);
            return;
        }

        $this->SendDebug('Topic', $topic, 0);
        $this->SendDebug('Payload', print_r($payload, true), 0);

        $groupTopic = $this->ReadPropertyString('GroupTopic');
        $devices = json_decode($this->ReadPropertyString('Devices'), true);

        foreach ($devices as $device) {
            $clientTopic = $device['MQTTTopic'] ?? '';
            $expectedPrefix = 'mqttsync/' . $groupTopic . '/' . $clientTopic;

            // Check if the message is for this client
            if (!str_starts_with($topic, $expectedPrefix)) {
                continue;
            }

            $suffix = substr($topic, strlen($expectedPrefix));

            // Handle /info message → store metadata in buffer
            if ($suffix === '/info') {
                if (isset($payload['Location'])) {
                    $this->SetBuffer($clientTopic . '_Location', $payload['Location']);
                }
                if (isset($payload['Description'])) {
                    $this->SetBuffer($clientTopic . '_Description', $payload['Description']);
                }
                if (isset($payload['InstallationDate'])) {
                    $this->SetBuffer($clientTopic . '_InstallDate', $payload['InstallationDate']);
                }
                if (isset($payload['IsActive'])) {
                    $this->SetBuffer($clientTopic . '_IsActive', (string)$payload['IsActive']);
                }
                $this->SendDebug('Client Info Stored', $clientTopic, 0);
            }

            // Handle /values message → update variables or create if missing
            elseif ($suffix === '/values') {
                foreach ($payload as $name => $entry) {
                    if (!isset($entry['id']) || !isset($entry['value'])) {
                        continue;
                    }

                    $varIdent = $clientTopic . '_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
                    $value = $entry['value'];

                    // Try to find existing variable
                    $varID = @IPS_GetObjectIDByIdent($varIdent, $this->InstanceID);
                    if ($varID === false) {
                        // Auto-detect type and create variable
                        if (is_bool($value)) {
                            $this->RegisterVariableBoolean($varIdent, $name, '', -1);
                        } elseif (is_int($value)) {
                            $this->RegisterVariableInteger($varIdent, $name, '', -1);
                        } elseif (is_float($value)) {
                            $this->RegisterVariableFloat($varIdent, $name, '', -1);
                        } else {
                            $this->RegisterVariableString($varIdent, $name, '', -1);
                        }
                        $varID = $this->GetIDForIdent($varIdent);
                    }

                    // Set the value
                    SetValue($varID, $value);
                    $this->SendDebug("Updated [$name]", "Value: $value", 0);
                }
            }

            // Unknown suffix
            else {
                $this->SendDebug('Unknown SubTopic', $suffix, 0);
            }
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

    public function sendConfigurationToClient()
    {
        $this->SendDebug('sendConfigurationToClient', 'Start', 0);

        $devices = json_decode($this->ReadPropertyString('Devices'), true);
        $retain = $this->ReadPropertyBoolean('Retain');

        foreach ($devices as $device) {
            if (!isset($device['MQTTTopic'])) {
                continue;
            }

            $payload = json_encode([
                'name' => $device['Name'] ?? '',
                'location' => $device['Location'] ?? '',
                'area' => $device['Area'] ?? 0,
                'description' => $device['Description'] ?? '',
                'type' => $device['Type'] ?? 'sensor'
            ]);

            $this->SendMQTT($device['MQTTTopic'], $payload, $retain);
        }

        $this->SendDebug('sendConfigurationToClient', 'Done', 0);
    }

    public function sendProfilesToClient()
    {
        $this->SendDebug('sendProfilesToClient', 'Start', 0);
        // Add logic if needed in future
        $this->SendDebug('sendProfilesToClient', 'Done', 0);
    }

    public function sendVariablesToClient()
    {
        $this->SendDebug('sendVariablesToClient', 'Start', 0);

        $devices = json_decode($this->ReadPropertyString('Devices'), true);
        $retain = $this->ReadPropertyBoolean('Retain');

        foreach ($devices as $device) {
            if (!isset($device['MQTTTopic']) || !isset($device['ObjectID'])) {
                continue;
            }

            $objectId = (int)$device['ObjectID'];
            if (!IPS_VariableExists($objectId)) {
                continue;
            }

            $value = GetValue($objectId);
            $valueJSON = json_encode($value);
            $this->SendMQTT($device['MQTTTopic'], $valueJSON, $retain);
        }

        $this->SendDebug('sendVariablesToClient', 'Done', 0);
    }

    public function synchronizeData()
    {
        $this->SendDebug('synchronizeData', 'Start', 0);
        $this->sendConfigurationToClient();
        $this->sendProfilesToClient();
        $this->sendVariablesToClient();
        $this->SendDebug('synchronizeData', 'Done', 0);
    }
}
