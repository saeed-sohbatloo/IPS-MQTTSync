<?php

declare(strict_types=1);

class IPS_MQTTSync extends IPSModule
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
        $this->RegisterPropertyString('GroupTopic', 'symcon');
        $this->RegisterPropertyBoolean('Retain', false);
        $this->RegisterPropertyString('Devices', '[]');
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');

        $MQTTTopic = $this->ReadPropertyString('GroupTopic');
        $this->SetReceiveDataFilter('.*mqttsync/' . $MQTTTopic . '.*');

        $DevicesJSON = $this->ReadPropertyString('Devices');
        if ($DevicesJSON != '') {
            $Devices = json_decode($DevicesJSON);
            foreach ($Devices as $key=>$Device) {
                $this->SendDebug(__FUNCTION__ . 'Devices', $Device->ObjectID . ' ' . $Device->MQTTTopic, 0);
                $Instanz = IPS_GetObject($Device->ObjectID);
                switch ($Instanz['ObjectType']) {
                    case 1:
                        foreach ($Instanz['ChildrenIDs'] as $Children) {
                            if (IPS_VariableExists($Children)) {
                                $this->RegisterMessage($Children, VM_UPDATE);
                            }
                        }
                        break;
                    case 2:
                        $this->SendDebug(__FUNCTION__, 'Script', 0);
                        if (IPS_VariableExists($Instanz['ObjectID'])) {
                            $this->RegisterMessage($Instanz['ObjectID'], VM_UPDATE);
                        }
                        break;
                    case 3:
                        $this->SendDebug(__FUNCTION__, 'Script', 0);
                        break;
                }
            }
        }
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        switch ($Message) {
            case VM_UPDATE:

                $Topic = '';
                $Instanz = null;
                $Object = IPS_GetObject($SenderID);

                if ($this->isInstance($SenderID)) {
                    $Topic = $this->TopicFromList($Object['ParentID']);
                    $PObject = IPS_GetObject($Object['ParentID']);

                    $i = 0;
                    foreach ($PObject['ChildrenIDs'] as $Children) {
                        if (IPS_VariableExists($Children)) {
                            $tmpObject = IPS_GetObject($Children);
                            $Instanz[$i]['ID'] = $tmpObject['ObjectID'];
                            $Instanz[$i]['Name'] = $tmpObject['ObjectName'];
                            $Instanz[$i]['Value'] = IPS_GetVariable($tmpObject['ObjectID'])['VariableType'];
                            $i++;
                        }
                    }
                } else {
                    $Topic = $this->TopicFromList($Object['ObjectID']);
                    $Instanz[0]['ID'] = $Object['ObjectID'];
                    $Instanz[0]['Name'] = $Object['ObjectName'];
                    $Instanz[0]['Value'] = GetValue($Object['ObjectID']);
                    $Instanz[0]['VariableTyp'] = IPS_GetVariable($Object['ObjectID'])['VariableType'];
                }

                if ($Instanz != null) {
                    $Payload = json_encode($Instanz);
                    $this->SendMQTTData($Topic, $Payload);
                }
                if ($Topic == '') {
                    $this->SendDebug(__FUNCTION__, 'Topic for Object ID: ' . $ObjectID . ' is not on list!', 0);
                }
        }
    }

    public function ReceiveData($JSONString)
    {
        $this->SendDebug('ReceiveData JSON', $JSONString, 0);
        $Data = json_decode($JSONString);

        if (property_exists($Data, 'Topic')) {
            $Topic = explode('/', $Data->Topic);
            $Topic = $Topic[array_key_last($Topic)];

            $this->SendDebug(__FUNCTION__ . ' Topic', $Topic, 0);
            $ObjectID = $this->isTopicFromList($Topic);
            if ($ObjectID != 0) {
                $this->SendDebug(__FUNCTION__ . 'Topic exists on list', $Data->Topic, 0);
                $Object = IPS_GetObject($ObjectID);
                switch ($Object['ObjectType']) {
                    case 3:
                        if ($Data->Payload == '') {
                            IPS_RunScript($ObjectID);
                        }
                        break;
                    default:
                        $this->SendDebug(__FUNCTION__ . 'No Action for ObjectType', $Object['ObjectType'], 0);
                        break;
                }
            }
        }
    }

    public function sendData(string $Payload)
    {
        $Topic = $this->TopicFromList($_IPS['SELF']);
        if ($Topic != '') {
            $this->SendMQTTData($Topic, $Payload);

            return true;
        }

        return false;
    }

    public function MQTTCommand(string $topic, string $payload)
    {
        $Data['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
        $Data['PacketType'] = 3;
        $Data['QualityOfService'] = 0;
        $Data['Retain'] = false;
        $Data['Topic'] = $topic;
        $Data['Payload'] = $payload;

        $DataJSON = json_encode($Data, JSON_UNESCAPED_SLASHES);
        $this->SendDebug(__FUNCTION__ . 'Topic', $Data['Topic'], 0);
        $this->SendDebug(__FUNCTION__, $DataJSON, 0);
        $this->SendDataToParent($DataJSON);
    }

    private function SendMQTTData(string $topic, string $payload)
    {
        $GroupTopic = $this->ReadPropertyString('GroupTopic');

        $Data['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
        $Data['PacketType'] = 3;
        $Data['QualityOfService'] = 0;
        $Data['Retain'] = $this->ReadPropertyBoolean('Retain');
        $Data['Topic'] = 'mqttsync/' . $GroupTopic . '/' . $topic;
        $Data['Payload'] = $payload;

        $DataJSON = json_encode($Data, JSON_UNESCAPED_SLASHES);
        $this->SendDebug(__FUNCTION__ . 'Topic', $Data['Topic'], 0);
        $this->SendDebug(__FUNCTION__, $DataJSON, 0);
        $this->SendDataToParent($DataJSON);
    }

    private function isInstance($ObjectID)
    {
        $Object = IPS_GetObject($ObjectID);

        if ($this->TopicFromList($Object['ParentID']) != '') {
            return true;
        }

        return false;
    }

    private function TopicFromList($ObjectID)
    {
        $DevicesJSON = $this->ReadPropertyString('Devices');
        $Devices = json_decode($DevicesJSON);
        foreach ($Devices as $Device) {
            if ($Device->ObjectID == $ObjectID) {
                return $Device->MQTTTopic;
            }
        }

        return '';
    }

    private function isTopicFromList($Topic)
    {
        $DevicesJSON = $this->ReadPropertyString('Devices');
        $Devices = json_decode($DevicesJSON);
        foreach ($Devices as $Device) {
            if ($Device->MQTTTopic == $Topic) {
                return $Device->ObjectID;
            }
        }
        $this->SendDebug(__FUNCTION__, 'Topic ' . $Topic . ' is not on list!', 0);

        return 0;
    }
}
