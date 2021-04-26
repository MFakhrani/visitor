<?php

namespace Fakhrani\Visitor;

use Carbon\Carbon as c;
use Countable;
use Illuminate\Support\Collection;
use Morilog\Jalali\Jalalian;
use Fakhrani\Visitor\Services\Cache\CacheInterface;
use Fakhrani\Visitor\Services\Geo\GeoInterface;
use Fakhrani\Visitor\Storage\VisitorInterface;
use Jenssegers\Agent\Agent;
/**
 * Class Visitor.
 */
class Visitor implements Countable
{
    /**
     * The Config array.
     *
     * @var string
     */
    protected $tableName = null;

    /**
     * The Option Repository Interface Instance.
     *
     * @var OpenInterface
     */
    protected $storage;

    /**
     * The Cache Interface.
     *
     * @var Fakhrani\Visitor\Services\Cache\CacheClass
     */
    protected $cache;

    /**
     * The Config Instance.
     *
     * @var Config
     */
    protected $collection;

    /**
     * @var Ip
     */
    protected $ip;

    /**
     * The Geo Interface.
     */
    protected $geo;

    /**
     * @param VisitorInterface $storage
     * @param GeoInterface     $geo
     * @param Ip               $ip
     * @param CacheInterface   $cache
     */
    public function __construct(
        VisitorInterface $storage,
        GeoInterface $geo,
        Ip $ip,
        CacheInterface $cache
    ) {
        $this->storage = $storage;
        $this->geo = $geo;
        $this->ip = $ip;
        $this->cache = $cache;

        $this->collection = new Collection();
    }

    /**
     * @param null $ip
     *
     * @return null
     */
    public function get($ip = null)
    {
        if (!isset($ip)) {
            $ip = $this->ip->get();
        }

        if ($this->ip->isValid($ip)) {
            return $this->storage->get($ip);
        }
    }

    public function log()
    {
        $ip = $this->ip->get();

        if (!$this->ip->isValid($ip)) {
            return;
        }

        $agent = new Agent();
        $is_mobile = ($agent->isMobile() ? 1 : 0);
        $visit = \App\Visitor::where('ip',$ip)->where('browser',$agent->browser())->where('is_mobile',$is_mobile)->where('created_at','>=',c::today())->first();
        if ($this->has($ip) && $visit != null) {
            //ip already exist in db.
            $visit->clicks += 1;
            $visit->update();
        } else {
            $geo = $this->geo->locate($ip);
            $country = array_key_exists('country_code', $geo) ? $geo['country_code'] : null;
            $state = array_key_exists('state', $geo) ? $geo['state'] : null;
            $city = array_key_exists('city', $geo) ? $geo['city'] : null;
            $browser = ($agent->browser() != null ? $agent->browser() : null);
            //ip doesnt exist  in db
            $data = [
                'ip'         => $ip,
                'country'    => $country,
                'state'    => $state,
                'city'    => $city,
                'clicks'     => 1,
                'browser' => $browser,
                'is_mobile' => $is_mobile,
                'updated_at' => c::now(),
                'created_at' => c::now(),
            ];
            $this->storage->create($data);
        }

        // Clear the database cache
        $this->cache->destroy('Fakhrani.visitor');
    }

    /**
     * @param $ip
     */
    public function forget($ip)
    {
        if (!$this->ip->isValid($ip)) {
            return;
        }

        //delete the ip from db
        $this->storage->delete($ip);

        // Clear the database cache
        $this->cache->destroy('Fakhrani.visitor');
    }

    /**
     * @param $ip
     *
     * @return bool
     */
    public function has($ip)
    {
        if (!$this->ip->isValid($ip)) {
            return false;
        }

        return $this->count($ip) > 0;
    }

    /**
     * @param null $ip
     *
     * @return mixed
     */
    public function count($ip = null)
    {
        //if ip null then return count of all visits
        return $this->storage->count($ip);
    }

    /**
     * @return mixed
     */
    public function all($collection = false)
    {
        $result = $this->cache->rememberForever('Fakhrani.visitor', $this->storage->all());

        if ($collection) {
            return $this->collection->make($result);
        }

        return $result;
    }

    /**
     * @return mixed
     */
    public function clicks()
    {
        return $this->storage->clicksSum();
    }

    /**
     * @param $start
     * @param $end
     *
     * @return mixed
     */
    public function range($start, $end)
    {
        $start = date('Y-m-d H:i:s', strtotime($start));
        $end = date('Y-m-d 23:59:59', strtotime($end));

        return $this->storage->range($start, $end);
    }

    /**
     * clear database records / cached results.
     *
     * @return void
     */
    public function clear()
    {
        //clear database
        $this->storage->clear();

        // clear cached options
        $this->cache->destroy('Fakhrani.visitor');
    }
}
