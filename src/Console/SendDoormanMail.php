<?php

namespace ImDong\BuyDoorman\Console;

use Flarum\Console\AbstractCommand;
use Flarum\Foundation\Paths;
use ImDong\BuyDoorman\BuyDoormanRecordRepository;
use Symfony\Component\Filesystem\Path;

class SendDoormanMail extends AbstractCommand
{
    /**
     * @var string 锁文件位置
     */
    private $lock_file = '';

    /**
     * @var int 发送邮件休息间隔时间 单位秒
     */
    private $send_sleep = 5;

    protected function configure()
    {
        $this
            ->setName('doorman:sendMail')
            ->setDescription('发送邀请码邮件');
    }

    /**
     * @return string 获取锁定文件位置
     */
    private function getLockFile(): string
    {
        if (empty($this->lock_file)) {
            $this->lock_file = resolve(Paths::class)->storage . '/cache/send-doorman-mail.lock';
        }

        return $this->lock_file;
    }

    protected function fire()
    {
        // 首先有一个文件锁
        $fd = fopen($this->getLockFile(), 'w+');
        if (!flock($fd, LOCK_EX | LOCK_NB)) {
            $this->error("is running...exit");

        }

        // 发送邮件
        $this->sendMail();

        // 解锁
        flock($fd, LOCK_UN);
    }

    /**
     *
     * 发送邮件
     *
     * @return void
     */
    private function sendMail()
    {
        // 获取对象
        $obj = resolve(BuyDoormanRecordRepository::class);

        // 获取待发送文件列表
        $dir = $obj->getCacheFile();
        $files = glob($dir);

        /**
         * 发送
         */
        $i = 0;

        foreach ($files as $file) {
            // 读取文件
            $data = json_decode(file_get_contents($file), true);

            // 发送记录
            $this->info(sprintf('send %s to %s from %s message %s', $data['key'], $data['email'], $data['nickname'], $data['message']));

            // 发送邮件
            $obj->sendInvites($data['email'], $data['key'], $data['message'], $data['nickname']);

            // 删除缓存文件
            unlink($file);

            // 休息几秒
            sleep($this->send_sleep);
        }


        var_dump($files);
    }
}