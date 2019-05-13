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
        $this->RegisterPropertyString('Devices', '[]');
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');

        $DevicesJSON = $this->ReadPropertyString('Devices');
        if ($DevicesJSON != '') {
            $Devices = json_decode($DevicesJSON);
            foreach ($Devices as $key=>$Device) {
                $this->SendDebug(__FUNCTION__ .'Devices', $Device->ObjectID . ' '.$Device->MQTTTopic,0);
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
                        $this->SendDebug(__FUNCTION__, 'Script',0);
                        if (IPS_VariableExists($Instanz['ObjectID'])) {
                            $this->RegisterMessage($Instanz['ObjectID'], VM_UPDATE);
                        }
                        break;
                    case 3:
                        $this->SendDebug(__FUNCTION__, 'Script',0);
                        break;
                }

                IPS_LogMessage(__FUNCTION__, print_r($Instanz,true));
            }
        }
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data) {
        switch ($Message)
        {
            case VM_UPDATE:

                $Topic = '';
                $Instanz = [];
                $VObject = IPS_GetObject($SenderID);
                $PObject = IPS_GetObject($VObject['ParentID']);
                $DevicesJSON = $this->ReadPropertyString('Devices');
                if ($DevicesJSON != '') {
                    $Devices = json_decode($DevicesJSON);
                    foreach ($Devices as $Device) {
                        if ($Device->ObjectID == $VObject['ParentID']) {
                            $Topic = $Device->MQTTTopic;
                        } else {
                            $this->SendDebug(__FUNCTION__,'No Topic for '. $VObject['ParentID'],0);
                        }
                    }
                }

                $i = 0;
                if ($Topic <> '') {
                    foreach ($PObject['ChildrenIDs'] as $Children) {
                        if (IPS_VariableExists($Children)) {
                            $tmpObject = IPS_GetObject($Children);
                            $Instanz[$i]['ID'] = $tmpObject['ObjectID'];
                            $Instanz[$i]['Name'] =  $tmpObject['ObjectName'];
                            $Instanz[$i]['Value'] = GetValue($tmpObject['ObjectID']);
                            $i++;
                        }
                    }
                } else {
                    $Devices = json_decode($DevicesJSON);
                    foreach ($Devices as $Device) {
                        if ($Device->ObjectID == $VObject['ObjectID']) {
                            $Topic = $Device->MQTTTopic;
                        } else {
                            $this->SendDebug(__FUNCTION__,'No Topic for '. $VObject['ParentID'],0);
                        }
                    }
                    $Instanz[$i]['ID'] = $VObject['ObjectID'];
                    $Instanz[$i]['Name'] =  $VObject['ObjectName'];
                    $Instanz[$i]['Value'] = GetValue($VObject['ObjectID']);
                }

                $Payload = json_encode($Instanz);

                $GroupTopic = $this->ReadPropertyString('GroupTopic');
                $Topic = $GroupTopic.'/'.$Topic;

                $this->SendData($Topic,$Payload);

                IPS_LogMessage('VM_UPDATE',print_r($Instanz,true));
                IPS_LogMessage('VM_UPDATE',$SenderID. ' '. $Data[0]);
        }
            //GetKernel()->PostMessage(VariableID, VM_UPDATE, VariableValue, HasDiff, OldValue, (int)Time, (int)OldUpdated, (int)OldChanged);
        //IPS_LogMessage("MessageSink", "Message from SenderID ".$SenderID." with Message ".$Message."\r\n Data: ".print_r($Data, true));
    }

    private function SendData(string $topic, string $payload) {
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
}
