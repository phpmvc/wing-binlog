<?php namespace Seals\Library;
/**
 * Created by PhpStorm.
 * User: yuyi
 * Date: 17/3/2
 * Time: 20:46
 *
 * 队列接口
 *
 */
interface QueueInterface
{

    /**
     * @加入到队列
     *
     * @param mixed $data 事件依附的数据
     * @return bool
     */
    public function push($data);

    /**
     * @弹出队列首部数据
     *
     * @return array
     */
    public function pop();

    /**
     * @只返回队首部元素 不弹出 不阻塞
     *
     * @return mixed
     */
    public function peek();

    /**
     * @返回消息队列长度
     *
     * @return int
     */
    public function length();

    /**
     * @清空队列
     *
     * @return bool
     */
    public function clear();
}