<?php

declare(strict_types=1);

class MQTTSyncClientConfigurator extends IPSModule
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent('{EE0D345A-CF31-428A-A613-33CE98E752DD}');
        $this->RegisterPropertyString('GroupTopic', 'symcon');
        $this->RegisterAttributeString('Devices', '[]');
    }

    public function GetConfigurationForm()
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $Devices = $this->ReadAttributeString('Devices');

        $Values = [];
        $Devices = json_decode($Devices);

        foreach ($Devices as $Device) {
            $instanceID = $this->getMQTTSyncClientDeviceInstance($Device->MQTTTopic);

            $tmpDevice = [];
            $tmpDevice = [
                'ObjectID'      => $Device->ObjectID,
                'ObjectName'    => $Device->ObjectName,
                'MQTTTopic'     => $Device->MQTTTopic,
                'ObjectType'    => $Device->ObjectType,
                'name'          => $Device->ObjectName,
                'instanceID'    => $instanceID

            ];
            $tmpDevice['create'] = [
                'moduleID'      => '{F6B7EB9F-7624-1026-44C1-9AF4059C26ED}',
                'configuration' => [
                    'MQTTTopic'    => $this->ReadPropertyString('GroupTopic').'/'.$Device->MQTTTopic,
                ],
            ];

            $Values[] = $tmpDevice;
        }

        $Form['actions'][0]['values'] = $Values;
        return json_encode($Form);
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $this->ConnectParent('{EE0D345A-CF31-428A-A613-33CE98E752DD}');

        $MQTTTopic = $this->ReadPropertyString('GroupTopic');
        $this->SetReceiveDataFilter('.*' . $MQTTTopic . '.*');
    }

    public function ReceiveData($JSONString)
    {
        $this->SendDebug('ReceiveData JSON', $JSONString, 0);
        $Data = json_decode($JSONString);
        $Buffer = json_decode($Data->Buffer);

        if (property_exists($Buffer, 'TOPIC')) {
            $arrTopic = explode('/', $Buffer->TOPIC);
            $CountItems = count($arrTopic);
            $Topic = $arrTopic[array_key_last($arrTopic)];
            if ($Topic == 'Configuration') {
                $Devices = json_decode($Buffer->MSG);
                $this->WriteAttributeString('Devices', $Buffer->MSG);
                $this->UpdateFormField('Devices', 'values', $Buffer->MSG);

                $this->SendDebug(__FUNCTION__, 'Topic: ' . 'Configuration ', 0);
            }
            if ($Topic == 'VariablenProfiles') {
                $Profiles = json_decode($Buffer->MSG, true);

                foreach ($Profiles as $Profile) {
                    $profileName = $Profile['ProfileName'];

                    IPS_CreateVariableProfile($profileName, $Profile['ProfileType']);
                    IPS_SetVariableProfileText($profileName, $Profile['Prefix'], $Profile['Suffix']);
                    IPS_SetVariableProfileValues($profileName, $Profile['MinValue'], $Profile['MaxValue'], $Profile['StepSize']);
                    IPS_SetVariableProfileDigits($profileName, $Profile['Digits']);
                    IPS_SetVariableProfileIcon($profileName, $Profile['Icon']);
                    foreach ($Profile['Associations'] as $association) {
                        IPS_SetVariableProfileAssociation($profileName, $association['Value'], $association['Name'], $association['Icon'], $association['Color']);
                    }
                }
            }
        }
    }

    private function getMQTTSyncClientDeviceInstance($Topic)
    {
        $InstanceIDs = IPS_GetInstanceListByModuleID('{F6B7EB9F-7624-1026-44C1-9AF4059C26ED}'); //MQTTSyncClientDevice
        foreach ($InstanceIDs as $id) {
            if (IPS_GetProperty($id, 'MQTTTopic') == $Topic) {
                if (IPS_GetInstance($id)['ConnectionID'] == IPS_GetInstance($this->InstanceID)['ConnectionID']) {
                    return $id;
                }
            }
        }
        return 0;
    }
}
