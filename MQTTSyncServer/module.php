<?php

declare(strict_types=1);

class MQTTTest extends IPSModule
{
    public function Create()
    {
        // Always call the parent Create() method first
        parent::Create();

        // Connect to the parent instance (typically an MQTT client)
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');

        // Register properties that match the form inputs
        $this->RegisterPropertyString('GroupTopic', 'symcon/stm');
        $this->RegisterPropertyBoolean('Retain', false);
        $this->RegisterPropertyString('Devices', '[]');
    }

    public function ApplyChanges()
    {
        // Always call the parent ApplyChanges() method first
        parent::ApplyChanges();

        // Decode the Devices JSON property
        $DevicesJSON = $this->ReadPropertyString('Devices');
        $Devices = json_decode($DevicesJSON);
        if (!is_array($Devices)) {
            // Invalid JSON or empty array, nothing to do
            return;
        }

        foreach ($Devices as $Device) {
            // Skip inactive devices
            if (!property_exists($Device, 'Active') || $Device->Active === false) {
                continue;
            }

            // Validate object existence
            if (!property_exists($Device, 'ObjectID') || !IPS_ObjectExists($Device->ObjectID)) {
                continue;
            }

            // Create or update variable profile for the device
            $profileName = 'MQTT_' . $Device->ObjectID;
            if (!IPS_VariableProfileExists($profileName)) {
                IPS_CreateVariableProfile($profileName, 1); // 1 = Integer profile type
                IPS_SetVariableProfileIcon($profileName, 'Network');
            }

            // Check profile type, throw exception if mismatch
            if (IPS_GetVariableProfile($profileName)['ProfileType'] !== 1) {
                throw new Exception('Variable profile type mismatch for profile: ' . $profileName);
            }

            $type = $Device->Type ?? 'sensor';

            switch ($type) {
                case 'sensor':
                    IPS_SetVariableProfileText($profileName, ' ', ' Unit');
                    break;
                case 'actuator':
                    IPS_SetVariableProfileText($profileName, 'Off', 'On');
                    break;
                case 'other':
                default:
                    IPS_SetVariableProfileText($profileName, '', '');
                    break;
            }
        }
    }

    public function ReceiveData(string $JSONString)
    {
        $Data = json_decode($JSONString, true);
        if ($Data === null) {
            return;
        }

        if (!isset($Data['Command'])) {
            return;
        }

        switch ($Data['Command']) {
            case 'SetValue':
                if (isset($Data['ObjectID']) && isset($Data['Value'])) {
                    $objectID = (int)$Data['ObjectID'];
                    $value = $Data['Value'];
                    if (IPS_VariableExists($objectID)) {
                        SetValue($objectID, $value);
                    }
                }
                break;
        }
    }

    public function SendMQTTData(string $Command, string $Payload)
    {
        $MQTTTopic = $this->ReadPropertyString('GroupTopic');
        $Retain = $this->ReadPropertyBoolean('Retain');

        $data = [
            'DataID' => '{46A53B59-99EF-4A15-B6D2-C2D8DBD92F76}', // MQTT DataID
            'MQTTTopic' => $MQTTTopic . '/' . $Command,
            'Payload' => $Payload,
            'Retain' => $Retain
        ];

        $this->SendDataToParent(json_encode($data));
    }

    // Send device configuration to the client
    public function sendConfiguration()
    {
        $DevicesJSON = $this->ReadPropertyString('Devices');
        $Devices = json_decode($DevicesJSON);
        if (!is_array($Devices)) {
            return;
        }

        $Configuration = [];
        foreach ($Devices as $Device) {
            if (!property_exists($Device, 'Active') || $Device->Active === false) {
                continue;
            }
            if (!property_exists($Device, 'ObjectID') || !IPS_ObjectExists($Device->ObjectID)) {
                continue;
            }
            $tmpConfiguration = [];
            $tmpConfiguration['ObjectID'] = $Device->ObjectID;
            $tmpConfiguration['ObjectName'] = IPS_GetObject($Device->ObjectID)['ObjectName'];
            $tmpConfiguration['MQTTTopic'] = $Device->MQTTTopic ?? '';
            $tmpConfiguration['Name'] = $Device->Name ?? '';
            $tmpConfiguration['Type'] = $Device->Type ?? '';
            $tmpConfiguration['ObjectType'] = IPS_GetObject($Device->ObjectID)['ObjectType'];
            $Configuration[] = $tmpConfiguration;
        }
        $this->SendMQTTData('Configuration', json_encode($Configuration));
    }

    // Send variable profiles to the client
    public function sendVariableProfiles()
    {
        // Example placeholder for sending variable profiles
        $Profiles = []; // Fill this array as needed
        $this->SendMQTTData('VariableProfiles', json_encode($Profiles));
    }

    // Send variables' current values to the client
    public function sendVariables()
    {
        $Variables = [];
        $DevicesJSON = $this->ReadPropertyString('Devices');
        $Devices = json_decode($DevicesJSON);
        if (!is_array($Devices)) {
            return;
        }

        foreach ($Devices as $Device) {
            if (!property_exists($Device, 'Active') || $Device->Active === false) {
                continue;
            }
            if (!property_exists($Device, 'ObjectID') || !IPS_ObjectExists($Device->ObjectID)) {
                continue;
            }
            $objectID = $Device->ObjectID;
            $Variables[$objectID] = GetValue($objectID);
        }
        $this->SendMQTTData('Variables', json_encode($Variables));
    }

    // Button handlers from the form

    public function synchronizeData()
    {
        $this->sendConfiguration();
        $this->sendVariableProfiles();
        $this->sendVariables();
    }

    public function sendConfigurationToClient()
    {
        $this->sendConfiguration();
    }

    public function sendProfilesToClient()
    {
        $this->sendVariableProfiles();
    }

    public function sendVariablesToClient()
    {
        $this->sendVariables();
    }
}
