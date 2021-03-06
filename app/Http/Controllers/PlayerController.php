<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery\Exception;
use Yajra\DataTables\DataTables;

class PlayerController extends Controller
{

    function isValidMd5($md5 ='') {
        return preg_match('/^[a-f0-9]{32}$/i', $md5);
    }

    function getTake($takereq)
    {
        if(isset($takereq))
        {
            return $takereq;
        }
        else
        {
            return 10;
        }
    }

    function getSkip($skipreq)
    {
        if(isset($skipreq))
        {
            return $skipreq;
        }
        else
        {
            return 0;
        }
    }

    function convertLicenseMREStoArray($licensestring)
    {
        if($licensestring == '"[]"' || $licensestring == '[]')
        {
            return null;
        }
        $licensestring = str_replace('"[[', '', $licensestring);
        $licensestring = str_replace(']]"', '', $licensestring);
        $licensestring = str_replace('`', '', $licensestring);
        $licensearray = explode('],[', $licensestring);
        $count = 0;
        foreach ($licensearray as $license)
        {

            $licenses[$count] = explode(',', $license);
            $count++;
        }
        return $licenses;
    }

    function threePartMREStoArray($string, $intval)
    {
        if($string == '"[]"')
        {
            return null;
        }
        $string = str_replace('"[', '', $string);
        $string = str_replace(']"', '', $string);
        $arr = explode(',', $string);

        if($intval)
        {
            $count = 0;
            foreach ($arr as $a)
            {
                $arr[$count] = intval($a);
                $count++;
            }
            
            if($count == 1)
            {
                $arr[2] = 0;
            }
            
        }
        return $arr;
    }











    public function version()
    {
        return env('VERSION', "1.4");
    }

    public function getPlayersLight(Request $request)
    {
        $type = $request->type;
        if (is_null($type)) $type = "all";
        if($type == "all")
        {
            $players = DB::table('players')->get();
        } elseif($type == "cops")
        {
            $players = DB::table('players')->where('coplevel', '>=', '1')->get();
        } elseif($type == "medics")
        {
            $players = DB::table('players')->where('mediclevel', '>=', '1')->get();
        } elseif($type == "opfors")
        {
            $players = DB::table('players')->where('uid', '<=', '0')->get();
        } elseif($type == "admins")
        {
            $players = DB::table('players')->where('adminlevel', '>=', '1')->get();
        } elseif($type == "donors")
        {
            $players = DB::table('players')->where('donorlevel', '>=', '1')->get();
        } elseif($type == "top10money")
        {
            $players = DB::table('players')->orderBy('bankacc', 'DESC')->take(10)->get();
        } elseif($type == "search" || $type == "search2") {
            $players = DB::table('players')
                ->where('name', 'LIKE', '%'.$request->q.'%')
                ->orWhere('uid', $request->q)
                ->orWhere('aliases', 'LIKE', '%'.$request->q.'%')
                ->orWhere(config('sharedapi.pid'), 'LIKE', '%'.$request->q.'%')
                ->get();
        }






        $output = [];
        $count = 0;
        foreach ($players as $player)
        {
            $output[$count]['uid'] = $player->uid;
            $output[$count]['name'] = $player->name;
            $pid = config('sharedapi.pid');
            $output[$count]['pid'] = $player->$pid;

            if ($type != "search")
            {
                $output[$count]['aliases'] = str_replace('`]"', '',str_replace('"[`', '', $player->aliases));
                $output[$count]['cash'] = $player->cash;
                $output[$count]['bank'] = $player->bankacc;
                $output[$count]['coplevel'] = intval($player->coplevel);
                $output[$count]['mediclevel'] = intval($player->mediclevel);
                $output[$count]['adminlevel'] = intval($player->adminlevel);
                $output[$count]['donorlevel'] = intval($player->donorlevel);
                $output[$count]['opforlevel']['enabled'] = config('sharedapi.opfor_enabled');
                if (config('sharedapi.opfor_enabled'))
                {
                    $opfor = config('sharedapi.opfor_level');
                    $output[$count]['opforlevel'] = intval($player->$opfor);
                }
                $output[$count]['arrested'] = intval($player->arrested);
                $output[$count]['playtime']['enabled'] = env('TABLE_PLAYERS_PLAYTIME_ENABLED', true);
                if (env('TABLE_PLAYERS_PLAYTIME_ENABLED', true))
                {
                    try {
                        $playtime = str_replace('"[', '', $player->playtime);
                        $playtime = str_replace(']"', '', $playtime);
                        $playtime = explode(',', $playtime);
                        $output[$count]['playtime']['civ'] = intval($playtime[2]);
                        $output[$count]['playtime']['cop'] = intval($playtime[0]);
                        $output[$count]['playtime']['med'] = intval($playtime[1]);
                    } catch (\Exception $e) {
                        $output[$count]['playtime']['civ'] = 0;
                        $output[$count]['playtime']['cop'] = 0;
                        $output[$count]['playtime']['med'] = 0;
                    }

                }
                if (env('TABLE_PLAYERS_TIMESTAMPS', true))
                {
                    $output[$count]['insert_time'] = $player->insert_time;
                    $output[$count]['last_seen'] = $player->last_seen;
                } else {
                    $output[$count]['insert_time'] = '0000-00-00 00:00:00';
                    $output[$count]['last_seen'] = '0000-00-00 00:00:00';
                }
            }

            $count++;
        }
        return $output;
    }

    public function getPlayersComplete()
    {
        $players = DB::table('players')->get();



        $count = 0;
        foreach ($players as $player)
        {
            $output[$count]['uid'] = $player->uid;
            $output[$count]['name'] = $player->name;
            $output[$count]['aliases'] = str_replace('`]"', '',str_replace('"[`', '', $player->aliases));
            $pid = config('sharedapi.pid');
            $output[$count]['pid'] = $player->$pid;
            $output[$count]['cash'] = $player->cash;
            $output[$count]['bank'] = $player->bankacc;
            $output[$count]['coplevel'] = intval($player->coplevel);
            $output[$count]['mediclevel'] = intval($player->mediclevel);
            $output[$count]['adminlevel'] = intval($player->adminlevel);
            $output[$count]['donorlevel'] = intval($player->donorlevel);
            $output[$count]['opforlevel']['enabled'] = config('sharedapi.opfor_enabled');
            if (config('sharedapi.opfor_enabled'))
            {
                $opfor = config('sharedapi.opfor_level');
                $output[$count]['opforlevel'] = intval($player->$opfor);
            }
            $output[$count]['arrested'] = intval($player->arrested);
            $output[$count]['blacklist'] = intval($player->blacklist);
            $output[$count]['civ_alive'] = intval($player->civ_alive);
            $output[$count]['playtime']['enabled'] = env('TABLE_PLAYERS_PLAYTIME_ENABLED', true);
            if (env('TABLE_PLAYERS_PLAYTIME_ENABLED', true))
            {
                $playtime = str_replace('"[', '', $player->playtime);
                $playtime = str_replace(']"', '', $playtime);
                $playtime = explode(',', $playtime);
                $output[$count]['playtime']['civ'] = intval($playtime[2]);
                $output[$count]['playtime']['cop'] = intval($playtime[0]);
                $output[$count]['playtime']['med'] = intval($playtime[1]);
            }
            $output[$count]['civ_licenses'] = $this->convertLicenseMREStoArray($player->civ_licenses);
            $output[$count]['cop_licenses'] = $this->convertLicenseMREStoArray($player->cop_licenses);
            $output[$count]['med_licenses'] = $this->convertLicenseMREStoArray($player->med_licenses);
            $output[$count]['civ_gear'] = $player->civ_gear;
            $output[$count]['cop_gear'] = $player->cop_gear;
            $output[$count]['med_gear'] = $player->med_gear;
            $output[$count]['stats']['enabled'] = true;
            $output[$count]['stats']['civ'] = $this->threePartMREStoArray($player->civ_stats, true);
            $output[$count]['stats']['cop'] = $this->threePartMREStoArray($player->cop_stats, true);
            $output[$count]['stats']['med'] = $this->threePartMREStoArray($player->med_stats, true);
            $output[$count]['pos']['enabled'] = true;
            $output[$count]['pos']['civ'] = $this->threePartMREStoArray($player->civ_position, false);
            if (env('TABLE_PLAYERS_TIMESTAMPS', true))
            {
                $output[$count]['insert_time'] = $player->insert_time;
                $output[$count]['last_seen'] = $player->last_seen;
            } else {
                $output[$count]['insert_time'] = '0000-00-00 00:00:00';
                $output[$count]['last_seen'] = '0000-00-00 00:00:00';
            }
            $count++;
        }
        return $output;
    }




    public function getPlayersSSP()
    {
        return DataTables::of(DB::table('players'))->toJson();




        $output = [];
        $count = 0;
        foreach ($players as $player)
        {
            $output[$count]['uid'] = $player->uid;
            $output[$count]['name'] = $player->name;
            $pid = config('sharedapi.pid');
            $output[$count]['pid'] = $player->$pid;

            if ($type != "search")
            {
                $output[$count]['aliases'] = str_replace('`]"', '',str_replace('"[`', '', $player->aliases));
                $output[$count]['cash'] = $player->cash;
                $output[$count]['bank'] = $player->bankacc;
                $output[$count]['coplevel'] = intval($player->coplevel);
                $output[$count]['mediclevel'] = intval($player->mediclevel);
                $output[$count]['adminlevel'] = intval($player->adminlevel);
                $output[$count]['donorlevel'] = intval($player->donorlevel);
                $output[$count]['opforlevel']['enabled'] = config('sharedapi.opfor_enabled');
                if (config('sharedapi.opfor_enabled'))
                {
                    $opfor = config('sharedapi.opfor_level');
                    $output[$count]['opforlevel'] = intval($player->$opfor);
                }
                $output[$count]['arrested'] = intval($player->arrested);
                $output[$count]['playtime']['enabled'] = env('TABLE_PLAYERS_PLAYTIME_ENABLED', true);
                if (env('TABLE_PLAYERS_PLAYTIME_ENABLED', true))
                {
                    try {
                        $playtime = str_replace('"[', '', $player->playtime);
                        $playtime = str_replace(']"', '', $playtime);
                        $playtime = explode(',', $playtime);
                        $output[$count]['playtime']['civ'] = intval($playtime[2]);
                        $output[$count]['playtime']['cop'] = intval($playtime[0]);
                        $output[$count]['playtime']['med'] = intval($playtime[1]);
                    } catch (\Exception $e) {
                        $output[$count]['playtime']['civ'] = 0;
                        $output[$count]['playtime']['cop'] = 0;
                        $output[$count]['playtime']['med'] = 0;
                    }

                }
                if (env('TABLE_PLAYERS_TIMESTAMPS', true))
                {
                    $output[$count]['insert_time'] = $player->insert_time;
                    $output[$count]['last_seen'] = $player->last_seen;
                } else {
                    $output[$count]['insert_time'] = '0000-00-00 00:00:00';
                    $output[$count]['last_seen'] = '0000-00-00 00:00:00';
                }
            }

            $count++;
        }
        return $output;
    }







    public function getPlayer(Request $request, $uid)
    {
        if(strlen($uid) == 17 && ctype_digit($uid))
        {
            $player = DB::table('players')->where(env('TABLE_PLAYERS_PID', config('sharedapi.pid')), $uid)->take(1)->get();
        }
        else {
            $player = DB::table('players')->where('uid', $uid)->take(1)->get();
        }

        if(is_null($player))
        {
            die('ss');
        }

        $output = [[]];

        $count = 0;
        foreach ($player as $player)
        {
            try {
                $output[$count]['uid'] = $player->uid;
            } catch (\Exception $e)
                {
                    $output[$count]['uid'] = $player->id;
                }
            
            $output[$count]['name'] = $player->name;
            $output[$count]['aliases'] = str_replace('`]"', '',str_replace('"[`', '', $player->aliases));
            $pid = config('sharedapi.pid');
            $output[$count]['pid'] = $player->$pid;


            $output[$count]['cash'] = $player->cash;
            $output[$count]['bank'] = $player->bankacc;
            $cop_cash = env('TABLE_PLAYERS_SEPCASH_COPCASH', 'cash');
            $cop_bank = env('TABLE_PLAYERS_SEPCASH_COPBANK', 'bankacc');
            $med_cash = env('TABLE_PLAYERS_SEPCASH_MEDCASH', 'cash');
            $med_bank = env('TABLE_PLAYERS_SEPCASH_MEDBANK', 'bankacc');
            $opfor_cash = env('TABLE_PLAYERS_SEPCASH_OPFORCASH', 'cash');
            $opfor_bank = env('TABLE_PLAYERS_SEPCASH_OPFORBANK', 'bankacc');
            $output[$count]['sepcash'] = env('TABLE_PLAYERS_SEPCASH', false);
            $output[$count]['cop_cash'] = $player->$cop_cash;
            $output[$count]['cop_bank'] = $player->$cop_bank;
            $output[$count]['med_cash'] = $player->$med_cash;
            $output[$count]['med_bank'] = $player->$med_bank;
            $output[$count]['opfor_cash'] = $player->$opfor_cash;
            $output[$count]['opfor_bank'] = $player->$opfor_bank;

            $output[$count]['coplevel'] = intval($player->coplevel);
            $output[$count]['mediclevel'] = intval($player->mediclevel);
            $output[$count]['adminlevel'] = intval($player->adminlevel);
            $output[$count]['donorlevel'] = intval($player->donorlevel);
            $output[$count]['extralevel1_enabled'] = config('sharedapi.extralevel_1');
            if (config('sharedapi.extralevel_1'))
            {
                $el1 = config('sharedapi.extralevel_1_column');
                $output[$count]['extralevel1'] = intval($player->$el1);
            }
            $output[$count]['extralevel2_enabled'] = config('sharedapi.extralevel_2');
            if (config('sharedapi.extralevel_2'))
            {
                $el2 = config('sharedapi.extralevel_2_column');
                $output[$count]['extralevel2'] = intval($player->$el2);
            }

            $output[$count]['opforlevel']['enabled'] = config('sharedapi.opfor_enabled');
            $output[$count]['opforlevel'] = 0;
            if (config('sharedapi.opfor_enabled'))
            {
                $opfor = config('sharedapi.opfor_level');
                $output[$count]['opforlevel'] = intval($player->$opfor);
            }
            $output[$count]['arrested'] = intval($player->arrested);
            $output[$count]['blacklist'] = intval($player->blacklist);
            $output[$count]['civ_alive'] = intval($player->civ_alive);
            $output[$count]['playtime']['enabled'] = env('TABLE_PLAYERS_PLAYTIME_ENABLED', true);
            if (env('TABLE_PLAYERS_PLAYTIME_ENABLED', true))
            {
                $playtime = str_replace('"[', '', $player->playtime);
                $playtime = str_replace(']"', '', $playtime);
                $playtime = explode(',', $playtime);
                try {
                    $output[$count]['playtime']['civ'] = intval($playtime[2]);
                    $output[$count]['playtime']['cop'] = intval($playtime[0]);
                    $output[$count]['playtime']['med'] = intval($playtime[1]);
                } catch (\Exception $e)
                {
                    $output[$count]['playtime']['civ'] = 0;
                    $output[$count]['playtime']['cop'] = 0;
                    $output[$count]['playtime']['med'] = 0;
                }

            }
            $output[$count]['civ_licenses'] = $this->convertLicenseMREStoArray($player->civ_licenses);
            try {
                $output[$count]['cop_licenses'] = $this->convertLicenseMREStoArray($player->cop_licenses);
                $output[$count]['med_licenses'] = $this->convertLicenseMREStoArray($player->med_licenses);
                $output[$count]['cop_licenses_string'] = $player->cop_licenses;
                $output[$count]['med_licenses_string'] = $player->med_licenses;
            } catch(\ErrorException $e) {
                $output[$count]['cop_licenses'] = null;
                $output[$count]['med_licenses'] = null;
                $output[$count]['cop_licenses_string'] = "";
                $output[$count]['med_licenses_string'] = "";
            }
                
            
            $output[$count]['opfor_licenses'] = null;
            $output[$count]['opfor_licenses_string'] = null;
            if (config('sharedapi.opfor_enabled'))
            {
                $opfl = config('sharedapi.opfor_licenses');
                $output[$count]['opfor_licenses'] = $this->convertLicenseMREStoArray($player->$opfl);
                $output[$count]['opfor_licenses_string'] = $player->$opfl;
            }

            $output[$count]['civ_licenses_string'] = $player->civ_licenses;
            

            $output[$count]['newgear'] = env('NEW_GEAR', false);
            $output[$count]['civ_gear'] = $player->civ_gear;
            $output[$count]['cop_gear'] = $player->cop_gear;
            $output[$count]['med_gear'] = $player->med_gear;
            $output[$count]['opfor_gear'] = '"[]"';
            if (config('sharedapi.opfor_enabled'))
            {
                $opfg = config('sharedapi.opfor_gear');
                $output[$count]['opfor_gear'] = $player->$opfg;
            }
            $output[$count]['stats']['enabled'] = true;
            $output[$count]['stats']['civ'][0] = 0;
            $output[$count]['stats']['civ'][1] = 0;
            $output[$count]['stats']['civ'][2] = 0;
            $output[$count]['stats']['civ'] = $this->threePartMREStoArray($player->civ_stats, true);
            $output[$count]['stats']['cop'][0] = 0;
            $output[$count]['stats']['cop'][1] = 0;
            $output[$count]['stats']['cop'][2] = 0;
            $output[$count]['stats']['cop'] = $this->threePartMREStoArray($player->cop_stats, true);
            $output[$count]['stats']['med'][0] = 0;
            $output[$count]['stats']['med'][1] = 0;
            $output[$count]['stats']['med'][2] = 0;
            $output[$count]['stats']['med'] = $this->threePartMREStoArray($player->med_stats, true);
            
            $output[$count]['pos']['enabled'] = true;
            $output[$count]['pos']['civ'] = $this->threePartMREStoArray($player->civ_position, false);
            if (env('TABLE_PLAYERS_TIMESTAMPS', true))
            {
                $output[$count]['insert_time'] = $player->insert_time;
                $output[$count]['last_seen'] = $player->last_seen;
            } else {
                $output[$count]['insert_time'] = '0000-00-00 00:00:00';
                $output[$count]['last_seen'] = '0000-00-00 00:00:00';
            }
            try {
                $output[$count]['aa_gangid'] = $player->gang_id;
            } catch (\Exception $e)
            {
                $output[$count]['aa_gangid'] = null;
            }
            $count++;
        }
        return $output[0];
    }

    public function getMoneySum()
    {
        $bank = $players = DB::table('players')->sum('bankacc');
        $cash = $players = DB::table('players')->sum('cash');
        return ($bank + $cash);
    }


    public function getDashboardStats()
    {
        $start = microtime(true);
        $bank = $players = DB::table('players')->sum('bankacc');
        $cash = $players = DB::table('players')->sum('cash');
        $output['money'] = $bank + $cash;
        $output['players'] = DB::table('players')->count();
        $output['cops'] = DB::table('players')->where('coplevel', '>=', 1)->count();
        $output['last7days'] = DB::table('players')->where('insert_time', '>=', Carbon::now()->subWeek())->get()->count();
        $output['last24hours'] = DB::table('players')->where('insert_time', '>=', Carbon::now()->subDay())->get()->count();
        $output['activelast48hours'] = DB::table('players')->where('last_seen', '>=', Carbon::now()->subDays(2))->get()->count();
        $output['activelast4hours'] = DB::table('players')->where('last_seen', '>=', Carbon::now()->subHours(4))->get()->count();
        $output['vehicles'] = DB::table('vehicles')->count();
        $output['vehicles_civ'] = DB::table('vehicles')->where('side', 'civ')->get()->count();
        $output['vehicles_cop'] = DB::table('vehicles')->where('side', 'cop')->get()->count();
        $output['vehicles_med'] = DB::table('vehicles')->where('side', 'med')->get()->count();
        $output['vehicles_active'] = DB::table('vehicles')->where('active', 1)->get()->count();
        $output['vehicles_alive'] = DB::table('vehicles')->where('alive', 1)->get()->count();
        $output['vehicles_last24hours'] = DB::table('vehicles')->where('insert_time', '>=', Carbon::now()->subDay())->get()->count();
        $output['vehicles_last7days'] = DB::table('vehicles')->where('insert_time', '>=', Carbon::now()->subWeek())->get()->count();
        $output['houses'] = DB::table('houses')->count();
        $output['gangs'] = DB::table('gangs')->count();
        $output['containers'] = DB::table('containers')->count();
        $output['totalBounty'] = intval(DB::table('wanted')->sum('wantedBounty'));
        $output['time'] = round((microtime(true) - $start) * 1000);
        return $output;
    }

    public function wipePlayer(Request $request, $uid) {
        $output['status'] = false;
        $type = $request['type'];
        $bank = $request['bank'];
        $pid = DB::table('players')->select(config('sharedapi.pid'))->where('uid',$uid)->get();
        $pid = json_decode($pid,true);
        $pid = $pid[0]['pid'];
        $output['steamid'] = $pid;
        $text = [];
        $val = 0;
        if($type == 1) {//Cash
            $money = DB::table('players')->select('bankacc','cash')->where('uid', $uid)->get();
            $money = json_decode($money,true);
            DB::table('players')->where('uid', $uid)->update([
                'bankacc' => $bank,
                'cash' => 0
            ]);
            if(0 != $money[0]['cash']) {
                $text = $text . "Changed cash from " . $money[0]['cash'] . "$ to 0$";
                $val++;
            }
            if($bank != $money[0]['bankacc']) {
                if($val > 0) {
                    $text = $text . "|";
                }
                $text = $text . "Changed bankacc from " . $money[0]['bankacc'] . "$ to " . $bank . "$";
            }
            $output['status'] = true;
        } elseif ($type == 2) {//Vehicles
            $text = DB::table('vehicles')->select('classname','id')->where('pid', $pid)->where('active','!=',2)->get();
            $text = json_decode($text,true);
            $text = json_encode($text);
            DB::table('vehicles')->where('pid', $pid)->update(
                ['active' => 2]
            );
            $output['status'] = true;
        } elseif ($type == 3) {//Houses
            $houses = DB::table('houses')->select('id')->where('pid', $pid)->get();
            DB::table('houses')->where('pid', $pid)->update(
                ['owned' => 2]
            );
            $houses = json_decode($houses,true);
            $text['houses'] = json_encode($houses);
            $containers = DB::table('containers')->select('id','classname')->where('pid', $pid)->get();
            DB::table('containers')->where('pid', $pid)->update(
                ['active' => 2]
            );
            $containers = json_decode($containers,true);
            $text['containers'] = json_encode($containers);
            $output['status'] = true;

        } elseif ($type == 4) {//All
            DB::table('players')->where('uid', $uid)->update([
                'cash' => 0,
                'bankacc' => $bank,
                'coplevel' => 0,
                'mediclevel' => 0,
                'civ_licenses' => '"[]"',
                'cop_licenses' => '"[]"',
                'med_licenses' => '"[]"',
                'civ_gear' => '"[]"',
                'cop_gear' => '"[]"',
                'med_gear' => '"[]"',
                'civ_stats' => '"[100,100,0]"',
                'cop_stats' => '"[100,100,0]"',
                'med_stats' => '"[100,100,0]"',
                'arrested' => 0,
                'adminlevel' => 0,
                'donorlevel' => 0,
                'blacklist' => 0,
                'civ_position' => '"[0,0,0]"'
            ]);
            DB::table('vehicles')->where('pid', $pid)->update(
                ['active' => 2]
            );
            DB::table('houses')->where('pid', $pid)->update(
                ['owned' => 2]
            );
            DB::table('containers')->where('pid', $pid)->update(
                ['owned' => 2]
            );
            $output['status'] = true;
        } elseif ($type == 5) {//delete
            try {
                DB::table('players')->where('uid', $uid)->delete();
                DB::table('vehicles')->where('pid', $pid)->delete();
                DB::table('houses')->where('pid', $pid)->delete();
                DB::table('containers')->where('pid', $pid)->delete();
                $output['status'] = true;
            } catch (Exception $e) {
                $output['status'] = false;
            }
        }
        $output['text'] = $text;
        return $output;
    }

    public function getlast30days() {
        $output = [];
        for ($i = 0; $i <= 30; $i++) {
            $datestring = Carbon::now()->subdays($i)->toDateString();
            $val = DB::table('players')->where(DB::raw('date(insert_time)'), $datestring)->get()->count();
            $val1 = DB::table('players')->where(DB::raw('date(insert_time)'), Carbon::now()->subdays($i)->toDateString())->where(DB::raw('date(last_seen)'),'>', DB::raw('date(insert_time)'))->get()->count();
            $output[$i][1] = $val;
            $output[$i][0] = $val1;
            $output[$i][2] = $datestring;
        }
        return $output;
    }
    public function getPossibleLevels()
    {
        $type = DB::select("SHOW COLUMNS FROM players WHERE Field = 'coplevel'")[0]->Type;
        preg_match("/^enum\(\'(.*)\'\)$/", $type, $matches);
        $type = explode("','", $matches[1]);
        $return['cop'] = intval(end($type));

        $type = DB::select("SHOW COLUMNS FROM players WHERE Field = 'mediclevel'")[0]->Type;
        preg_match("/^enum\(\'(.*)\'\)$/", $type, $matches);
        $type = explode("','", $matches[1]);
        $return['med'] = intval(end($type));

        $type = DB::select("SHOW COLUMNS FROM players WHERE Field = 'adminlevel'")[0]->Type;
        preg_match("/^enum\(\'(.*)\'\)$/", $type, $matches);
        $type = explode("','", $matches[1]);
        $return['admin'] = intval(end($type));

        $type = DB::select("SHOW COLUMNS FROM players WHERE Field = 'donorlevel'")[0]->Type;
        preg_match("/^enum\(\'(.*)\'\)$/", $type, $matches);
        $type = explode("','", $matches[1]);
        $return['donor'] = intval(end($type));

        $return['opfor'] = -1;
        if (config('sharedapi.opfor_enabled'))
        {
            $type = DB::select("SHOW COLUMNS FROM players WHERE Field = '".config('sharedapi.opfor_level')."'")[0]->Type;
            preg_match("/^enum\(\'(.*)\'\)$/", $type, $matches);
            $type = explode("','", $matches[1]);
            $return['opfor'] = intval(end($type));
        }

        $return['extralevel1'] = -1;
        if (env('TABLE_PLAYERS_EXTRALEVEL_1', false))
        {
            $type = DB::select("SHOW COLUMNS FROM players WHERE Field = '".env('TABLE_PLAYERS_EXTRALEVEL_1_column')."'")[0]->Type;
            preg_match("/^enum\(\'(.*)\'\)$/", $type, $matches);
            $type = explode("','", $matches[1]);
            $return['extralevel1'] = intval(end($type));
        }

        $return['extralevel2'] = -1;
        if (env('TABLE_PLAYERS_EXTRALEVEL_2', false))
        {
            $type = DB::select("SHOW COLUMNS FROM players WHERE Field = '".env('TABLE_PLAYERS_EXTRALEVEL_2_column')."'")[0]->Type;
            preg_match("/^enum\(\'(.*)\'\)$/", $type, $matches);
            $type = explode("','", $matches[1]);
            $return['extralevel2'] = intval(end($type));
        }


        return $return;
    }


    /**
     * Changes gear in DB if changed and returns changed values
     * Expects all 4 gear params and the DB UID
     */
    public function editPlayerGear(Request $request, $uid)
    {
        if(is_null($request->civ_gear) || is_null($request->cop_gear) || is_null($request->med_gear) || is_null($request->opfor_gear))
        {
            die('false');
        }

        $players = DB::table('players')->where('uid', $uid)->take(1)->get();
        foreach ($players as $p)
        {
            $player = $p;
        }
        $Gear['civ']['pre'] = $player->civ_gear;
        $Gear['cop']['pre'] = $player->cop_gear;
        $Gear['med']['pre'] = $player->med_gear;
        $Gear['opfor']['pre'] = '"[]"';
        if (config('sharedapi.opfor_enabled'))
        {
            $opfg = config('sharedapi.opfor_gear');
            $Gear['opfor']['pre'] = $player->$opfg;
        }

        $Gear['civ']['post'] = $request->civ_gear;
        $Gear['cop']['post'] = $request->cop_gear;
        $Gear['med']['post'] = $request->med_gear;
        $Gear['opfor']['post'] = $request->opfor_gear;

        if($Gear['civ']['pre'] == $Gear['civ']['post'])
        {
            $Gear['civ']['changed'] = false;
            unset($Gear['civ']['pre']);
            unset($Gear['civ']['post']);
        } else {
            $Gear['civ']['changed'] = true;
            $players = DB::table('players')->where('uid', $uid)->update(['civ_gear' => $Gear['civ']['post']]);
        }
        if($Gear['cop']['pre'] == $Gear['cop']['post'])
        {
            $Gear['cop']['changed'] = false;
            unset($Gear['cop']['pre']);
            unset($Gear['cop']['post']);
        } else {
            $Gear['cop']['changed'] = true;
            $players = DB::table('players')->where('uid', $uid)->update(['cop_gear' => $Gear['cop']['post']]);
        }
        if($Gear['med']['pre'] == $Gear['med']['post'])
        {
            $Gear['med']['changed'] = false;
            unset($Gear['med']['pre']);
            unset($Gear['med']['post']);
        } else {
            $Gear['med']['changed'] = true;
            $players = DB::table('players')->where('uid', $uid)->update(['med_gear' => $Gear['med']['post']]);
        }
        $Gear['opfor']['changed'] = false;
        if (config('sharedapi.opfor_enabled'))
        {
            if($Gear['opfor']['pre'] == $Gear['opfor']['post'])
            {
                $Gear['opfor']['changed'] = false;
                unset($Gear['opfor']['pre']);
                unset($Gear['opfor']['post']);
            } else {
                $Gear['opfor']['changed'] = true;
                $players = DB::table('players')->where('uid', $uid)->update([config('sharedapi.opfor_gear') => $Gear['opfor']['post']]);
            }
        }

        return $Gear;
    }

    public function editPlayerLevel(Request $request, $uid)
    {
        $players = DB::table('players')->where('uid', $uid)->take(1)->get();
        foreach ($players as $p)
        {
            $player = $p;
        }
        $level['cop']['pre'] = $player->coplevel;
        $level['med']['pre'] = $player->mediclevel;
        $level['opfor']['pre'] = 0;
        if (config('sharedapi.opfor_enabled'))
        {
            $opfor = config('sharedapi.opfor_level');
            $level['opfor']['pre'] = $player->$opfor;
        }

        $level['admin']['pre'] = $player->adminlevel;
        $level['donor']['pre'] = $player->donorlevel;
        $level['blacklist']['pre'] = $player->blacklist;
        $level['arrested']['pre'] = $player->arrested;

        if(isset($request->cop))
        {
            if($request->cop == $level['cop']['pre'])
            {
                $level['cop']['changed'] = false;
                unset($level['cop']['pre']);
            } else {
                $level['cop']['post'] = $request->cop;
                $level['cop']['changed'] = true;
                DB::table('players')->where('uid', $uid)->update(['coplevel' => $level['cop']['post']]);
            }
        } else {
            $level['cop']['changed'] = false;
            unset($level['cop']['pre']);
        }
        if(isset($request->med))
        {
            if($request->med == $level['med']['pre'])
            {
                $level['med']['changed'] = false;
                unset($level['med']['pre']);
            } else {
                $level['med']['post'] = $request->med;
                $level['med']['changed'] = true;
                DB::table('players')->where('uid', $uid)->update(['mediclevel' => $level['med']['post']]);
            }
        } else {
            $level['med']['changed'] = false;
            unset($level['med']['pre']);
        }
        if(isset($request->opfor))
        {
            if($request->opfor == $level['opfor']['pre'])
            {
                $level['opfor']['changed'] = false;
                unset($level['opfor']['pre']);
            } else {
                if (config('sharedapi.opfor_enabled'))
                {
                    $level['opfor']['post'] = $request->opfor;
                    $level['opfor']['changed'] = true;
                    DB::table('players')->where('uid', $uid)->update([config('sharedapi.opfor_level') => $level['opfor']['post']]);
                } else {
                    $level['opfor']['post'] = $request->opfor;
                    $level['opfor']['changed'] = false;
                }
            }
        } else {
            $level['opfor']['changed'] = false;
            unset($level['opfor']['pre']);
        }
        if(isset($request->admin))
        {
            if($request->admin == $level['admin']['pre'])
            {
                $level['admin']['changed'] = false;
                unset($level['admin']['pre']);
            } else {
                $level['admin']['post'] = $request->admin;
                $level['admin']['changed'] = true;
                DB::table('players')->where('uid', $uid)->update(['adminlevel' => $level['admin']['post']]);
            }
        } else {
            $level['admin']['changed'] = false;
            unset($level['admin']['pre']);
        }
        if(isset($request->donor))
        {
            if($request->donor == $level['donor']['pre'])
            {
                $level['donor']['changed'] = false;
                unset($level['donor']['pre']);
            } else {
                $level['donor']['post'] = $request->donor;
                $level['donor']['changed'] = true;
                DB::table('players')->where('uid', $uid)->update(['donorlevel' => $level['donor']['post']]);
            }
        } else {
            $level['donor']['changed'] = false;
            unset($level['donor']['pre']);
        }
        if(isset($request->blacklist))
        {
            if($request->blacklist == $level['blacklist']['pre'])
            {
                $level['blacklist']['changed'] = false;
                unset($level['blacklist']['pre']);
            } else {
                $level['blacklist']['post'] = $request->blacklist;
                $level['blacklist']['changed'] = true;
                DB::table('players')->where('uid', $uid)->update(['blacklist' => $level['blacklist']['post']]);
            }
        } else {
            $level['blacklist']['changed'] = false;
            unset($level['blacklist']['pre']);
        }
        if(isset($request->arrested))
        {
            if($request->arrested == $level['arrested']['pre'])
            {
                $level['arrested']['changed'] = false;
                unset($level['arrested']['pre']);
            } else {
                $level['arrested']['post'] = $request->arrested;
                $level['arrested']['changed'] = true;
                DB::table('players')->where('uid', $uid)->update(['arrested' => $level['arrested']['post']]);
            }
        } else {
            $level['arrested']['changed'] = false;
            unset($level['arrested']['pre']);
        }
        return $level;
    }

    public function editPlayerLicenses(Request $request, $uid)
    {
        $players = DB::table('players')->where('uid', $uid)->take(1)->get();
        foreach ($players as $p)
        {
            $player = $p;
        }
        if (isset($request->civ))
        {
            DB::table('players')->where('uid', $uid)->update(['civ_licenses' => $request->civ]);
        }
        if (isset($request->cop))
        {
            try {
                DB::table('players')->where('uid', $uid)->update(['cop_licenses' => $request->cop]);
            } catch (\Exception $e) {
                   
            }
        }
        if (isset($request->med))
        {
            try {
                DB::table('players')->where('uid', $uid)->update(['med_licenses' => $request->med]);
            } catch (\Exception $e) {
                   
            }
        }
        if (isset($request->opfor) && config('sharedapi.opfor_enabled'))
        {
            DB::table('players')->where('uid', $uid)->update([config('sharedapi.opfor_licenses') => $request->opfor]);
        }
    }

    public function editPlayerMoney(Request $request, $uid)
    {
        $players = DB::table('players')->where('uid', $uid)->take(1)->get();
        foreach ($players as $p)
        {
            $player = $p;
            $preBank = $p->bankacc;
            $preCash = $p->cash;
            if (env('TABLE_PLAYERS_SEPCASH', false))
            {
                $cop_cash = env('TABLE_PLAYERS_SEPCASH_COPCASH', 'cash');
                $cop_bank = env('TABLE_PLAYERS_SEPCASH_COPBANK', 'bankacc');
                $med_cash = env('TABLE_PLAYERS_SEPCASH_MEDCASH', 'cash');
                $med_bank = env('TABLE_PLAYERS_SEPCASH_MEDBANK', 'bankacc');
                $opfor_cash = env('TABLE_PLAYERS_SEPCASH_OPFORCASH', 'cash');
                $opfor_bank = env('TABLE_PLAYERS_SEPCASH_OPFORBANK', 'bankacc');
                $output['cop_cash'] = $p->$cop_cash;
                $output['cop_bank'] = $p->$cop_bank;
                $output['med_cash'] = $p->$med_cash;
                $output['med_bank'] = $p->$med_bank;
                $output['opfor_cash'] = $p->$opfor_cash;
                $output['opfor_bank'] = $p->$opfor_bank;
            }
        }
        DB::table('players')->where('uid', $uid)->update(['bankacc' => $request->bank, 'cash' => $request->cash]);

        $toLog['bank']['pre'] = $preBank;
        $toLog['bank']['post'] = intval($request->bank);
        $toLog['bank']['change'] = $request->bank - $preBank;
        $toLog['cash']['pre'] = $preCash;
        $toLog['cash']['post'] = intval($request->cash);
        $toLog['cash']['change'] = $request->cash - $preCash;

        if (env('TABLE_PLAYERS_SEPCASH', false))
        {
            DB::table('players')->where('uid', $uid)->update([
                env('TABLE_PLAYERS_SEPCASH_COPCASH') => $request->copcash,
                env('TABLE_PLAYERS_SEPCASH_COPBANK') => $request->copbank,
                env('TABLE_PLAYERS_SEPCASH_MEDCASH') => $request->medcash,
                env('TABLE_PLAYERS_SEPCASH_MEDBANK') => $request->medbank,
                env('TABLE_PLAYERS_SEPCASH_OPFORCASH') => $request->opforcash,
                env('TABLE_PLAYERS_SEPCASH_OPFORBANK') => $request->opforbank
                ]);
            $toLog['cop_bank']['pre'] = $output['cop_bank'];
            $toLog['cop_cash']['pre'] = $output['cop_cash'];
            $toLog['med_bank']['pre'] = $output['med_bank'];
            $toLog['med_cash']['pre'] = $output['med_cash'];
            $toLog['opfor_bank']['pre'] = $output['opfor_bank'];
            $toLog['opfor_cash']['pre'] = $output['opfor_cash'];

            $toLog['cop_bank']['post'] = $request->copbank;
            $toLog['cop_cash']['post'] = $request->copcash;
            $toLog['med_bank']['post'] = $request->medbank;
            $toLog['med_cash']['post'] = $request->medcash;
            $toLog['opfor_bank']['post'] = $request->opforbank;
            $toLog['opfor_cash']['post'] = $request->opforcash;

            $toLog['cop_bank']['change'] = $request->copbank - $toLog['cop_bank']['pre'];
            $toLog['cop_cash']['change'] = $request->copcash - $toLog['cop_cash']['pre'];
            $toLog['med_bank']['change'] = $request->medbank - $toLog['med_bank']['pre'];
            $toLog['med_cash']['change'] = $request->medcash - $toLog['med_cash']['pre'];
            $toLog['opfor_bank']['change'] = $request->opforbank - $toLog['opfor_bank']['pre'];
            $toLog['opfor_cash']['change'] = $request->opforcash - $toLog['opfor_cash']['pre'];


        }
        return $toLog;

    }

    public function editOtherData(Request $request, $uid)
    {
        $players = DB::table('players')->where('uid', $uid)->take(1)->get();
        foreach ($players as $p)
        {
            $player = $p;
            $preName = $p->name;
        }
        DB::table('players')->where('uid', $uid)->update(['name' => $request->name]);

        $toLog['name']['pre'] = $preName;
        $toLog['name']['post'] = $request->name;
        if ($toLog['name']['pre'] == $toLog['name']['post'])
        {
            $toLog['name']['changed'] = false;
        } else {
            $toLog['name']['changed'] = true;
        }
        return $toLog;
    }

    public function getCustomFields(Request $request, $uid)
    {
        if (strlen($uid) >= 11)
        {
            $players = DB::table('players')->where(config('sharedapi.pid'), $uid)->take(1)->get();    
        } else {
            $players = DB::table('players')->where('uid', $uid)->take(1)->get();
        }
        if(is_null($players)) abort(404);
        $fields = explode(',', $request->fields);

        foreach ($players as $p)
        {
            foreach ($fields as $field)
            {
                $output[$field] = $p->$field;
            }
        }
        return $output;
    }

    public function changeCustomFields(Request $request, $uid)
    {
        if (strlen($uid) >= 11)
        {
            $players = DB::table('players')->where(config('sharedapi.pid'), $uid)->take(1)->get();    
        } else {
            $players = DB::table('players')->where('uid', $uid)->take(1)->get();
        }
        if(is_null($players)) abort(404);
        $fields = explode(',', $request->fields);

        foreach ($players as $p)
        {
            foreach ($fields as $field)
            {
                $output['pre'][$field] = $p->$field;
                if (is_null($request->$field))
                {
                    $output['post'][$field] = null;
                } else {
                    if (strlen($uid) >= 11)
                    {
                        DB::table('players')->where(config('sharedapi.pid'), $uid)->update([$field => $request->$field]); 
                    } else {
                        DB::table('players')->where('uid', $uid)->update([$field => $request->$field]);
                    }
                    //DB::table('players')->where('uid', $uid)->update([$field => $request->$field]);
                    $output['post'][$field] = $request->$field;
                }

            }
        }
        return $output;
    }

}
