<?php

declare(strict_types=1);

class MQTTSyncClientDevice extends IPSModule
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->ConnectParent('{EE0D345A-CF31-428A-A613-33CE98E752DD}');
        $this->RegisterPropertyString('MQTTTopic', '');
        $this->RegisterPropertyString('GroupTopic', '');
        
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $this->ConnectParent('{EE0D345A-CF31-428A-A613-33CE98E752DD}');

        $GroupTopic = $this->ReadPropertyString('GroupTopic');
        $MQTTTopic = $this->ReadPropertyString('MQTTTopic');
        $this->SetReceiveDataFilter('.*mqttsync/'.$GroupTopic.'/'.$MQTTTopic.'.*');
        //$this->SetReceiveDataFilter('.*' . $MQTTTopic . '.*');

    }

    public function ReceiveData($JSONString)
    {
        $this->SendDebug('ReceiveData JSON', $JSONString, 0);
        $Data = json_decode($JSONString);
        $Buffer = json_decode($Data->Buffer);

        if (property_exists($Buffer, 'TOPIC')) {
            $Variablen = json_decode($Buffer->MSG);
            foreach ($Variablen as $Variable) {
                IPS_LogMessage('Test', print_r($Variable,true));
                if ($Variable->ObjectIdent == '') {
                    $ObjectIdent = $Variable->ID;
                } else {
                    $ObjectIdent = $Variable->ObjectIdent;
                }
                $ID = $this->GetIDForIdent($ObjectIdent);
                if (!$ID) {
                    switch ($Variable->VariableTyp) {
                        case 0:
                            $this->RegisterVariableBoolean($ObjectIdent, $Variable->Name, $Variable->VariableProfile);
                            break;
                        case 1:
                            $this->RegisterVariableInteger($ObjectIdent, $Variable->Name, $Variable->VariableProfile);
                            break;
                        case 2:
                            $this->RegisterVariableFloat($ObjectIdent, $Variable->Name, $Variable->VariableProfile);
                            break;
                        case 3:
                            $this->RegisterVariableString($ObjectIdent, $Variable->Name, $Variable->VariableProfile);
                            break;
                        default:
                            IPS_LogMessage('MQTTSync Client', 'invalid variablen profile');
                            break;
                    }
                    if ($Variable->VariableAction != 0) {
                        $this->EnableAction($ObjectIdent);
                    }
                }
                $this->SendDebug('Value for ' . $ObjectIdent . ':', $Variable->Value, 0);
                $this->SetValue($ObjectIdent, $Variable->Value);
            }
        }
    }

    public function RequestAction($Ident, $Value)
    {
        $MSG = [];
        $MSG['ObjectIdent'] = $Ident;
        $MSG['Value'] = $Value;
        $Topic = 'mqttsync/'. $this->ReadPropertyString('MQTTTopic').'/set';
        $this->sendMQTTCommand($Topic, json_encode($MSG));

        //$this->SetValue($Ident,$Value);

    }

    protected function sendMQTTCommand($topic, $msg, $retain = 0)
    {
        $Buffer['Topic'] = $topic;
        $Buffer['MSG'] = $msg;
        $Buffer['Retain'] = $retain;
        $BufferJSON = json_encode($Buffer);
        $this->SendDebug('sendMQTTCommand Buffer', $BufferJSON, 0);
        $this->SendDataToParent(json_encode(['DataID' => '{018EF6B5-AB94-40C6-AA53-46943E824ACF}', 'Action' => 'Publish', 'Buffer' => $BufferJSON]));
    }
}
