<?php

declare(strict_types=1);

class MQTTSyncClientDevice extends IPSModule
{
    public function Create()
    {
        parent::Create();

        // Properties قابل تنظیم توسط کاربر
        $this->RegisterPropertyString('MQTTTopic', '');
        $this->RegisterPropertyString('GroupTopic', '');
        $this->RegisterPropertyString('Location', '');
        $this->RegisterPropertyInteger('Area', 0);
        $this->RegisterPropertyString('Description', '');
        $this->RegisterPropertyString('InstallationDate', '');
        $this->RegisterPropertyBoolean('IsActive', true);
        $this->RegisterPropertyInteger('SendInterval', 10);  // زمان ارسال به ثانیه

        // تایمر ارسال اطلاعات
        $this->RegisterTimer('SendInfoTimer', 0, 'MQTTSYNC_SendDeviceInfoMQTT($_IPS["TARGET"]);');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // فیلتر داده‌های ورودی
        $group = $this->ReadPropertyString('GroupTopic');
        $topic = $this->ReadPropertyString('MQTTTopic');
        $this->SetReceiveDataFilter('.*mqttsync/' . preg_quote($group, '/') . '/' . preg_quote($topic, '/') . '.*');

        // فعال یا غیرفعال کردن تایمر
        if ($this->ReadPropertyBoolean('IsActive')) {
            $interval = $this->ReadPropertyInteger('SendInterval');
            $interval = max(1, $interval); // حداقل ۱ ثانیه
            $this->SetTimerInterval('SendInfoTimer', $interval * 1000);
        } else {
            $this->SetTimerInterval('SendInfoTimer', 0);
        }
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

        $topic = 'mqttsync/' . $payload['GroupTopic'] . '/' . $payload['MQTTTopic'] . '/info';
        $this->SendMQTT($topic, json_encode($payload));
    }

    protected function SendMQTT(string $topic, string $payload, bool $retain = false)
    {
        $data = [
            'DataID' => '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}',
            'PacketType' => 3,
            'QualityOfService' => 0,
            'Retain' => $retain,
            'Topic' => $topic,
            'Payload' => $payload
        ];
        $json = json_encode($data);
        $this->SendDebug('SendMQTT', $json, 0);
        $this->SendDataToParent($json);
    }
}
