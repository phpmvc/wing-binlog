<?php
if (!isset($_GET["group_id"])) {
    echo "params error";
    return;
}
$group_id   = $_GET["group_id"];
$session_id = \Seals\Library\Zookeeper::getLeader($group_id);
$node_info  = \Seals\Web\Logic\Node::getInfo($group_id, $session_id);
$databases  = \Seals\Web\Logic\Node::getDatabases($session_id);
?>
<?php include  __DIR__."/include/nav.php"; ?>

<script>
    var group_id   = "<?php echo $_GET["group_id"]; ?>";
    var session_id = "<?php echo $session_id; ?>";
</script>
<div class="right_col" role="main">
    <div class="">
        <div class="page-title">
            <div class="title_left">
                <h3>__LANG(Group Configure)</h3>
            </div>
        </div>
        <div class="clearfix"></div>
        <div style="padding: 0 0 20px;">
            <small style="color: #f00; background: #FFFF00;    padding: 8px 25px;
    width: 100%;
    display: block;    border: 1px solid #E6E9ED;">
                __LANG(set the group configure will change all the nodes in the group, default show the group leader configure info)<br/>
                __LANG(all the password are remove from the form, so you need to input the complete password for change configure)
            </small>
        </div>
        <div class="row">
            <div class="col-md-6 col-xs-12">
                <!--Process runtime configure-->
                <div class="x_panel">
                    <div class="x_title">
                        <h2>__LANG(Process Runtime Configure)</h2>
                        <div class="clearfix"></div>
                    </div>
                    <div class="x_content">

                        <!-- start form for validation -->
                        <div class="c-item form-horizontal form-label-left">
                            <div class="form-group">
                                <label class="control-label col-md-3 col-sm-3 col-xs-12">__LANG(Workers Num)</label>
                                <div class="col-md-9 col-sm-9 col-xs-12">
                                    <input type="text" class="form-control workers" value="<?php echo $node_info["workers"]; ?>" placeholder="Workers Num">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label col-md-3 col-sm-3 col-xs-12">__LANG(Debug)</label>
                                <div class="col-md-9 col-sm-9 col-xs-12">
                                    <div class="">
                                        <label>
                                            <input type="checkbox" class="js-switch debug" <?php if($node_info["debug"])echo "checked";?> data-switchery="true" style="display: none;">
                                            __LANG(Enable)
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="ln_solid"></div>
                            <div class="form-group">
                                <div class="col-md-9 col-sm-9 col-xs-12 col-md-offset-3">
                                    <button type="button" onclick="setRuntimeConfig(this)" class="btn btn-success">__LANG(Update Configure)</button>
                                </div>
                            </div>
                        </div>
                        <!-- end form for validations -->

                    </div>
                </div>
                <div class="x_panel">
                    <div class="x_title">
                        <h2>__LANG(Notify Configure)</h2>
                        <div class="clearfix"></div>
                    </div>
                    <div class="x_content">
                        <div class="c-item form-horizontal form-label-left">
                            <div class="form-group">
                                <label class="control-label col-md-3 col-sm-3 col-xs-12">__LANG(Notify Mode)</label>
                                <div class="col-md-9 col-sm-9 col-xs-12">
                                    <select class="notify-class form-control" onchange="onNotifySelect(this)">
                                        <option data-config-class="event-redis-config"
                                                data-param-1="<?php
                                        if ($node_info["notify"]["handler"] == "Seals\\Notify\\Redis")
                                            echo $node_info["notify"]["params"][0];
                                        else
                                            echo "seals:event:list";
                                        ?>"
                                                data-param-2=""
                                                value="Seals\\Notify\\Redis"
                                            <?php if ($node_info["notify"]["handler"] == "Seals\\Notify\\Redis") echo "selected"; ?>
                                        >redis queue</option>
                                        <option  data-config-class=""
                                                 data-param-1="<?php
                                                 if ($node_info["notify"]["handler"] == "Seals\\Notify\\Http")
                                                     echo $node_info["notify"]["params"][0];
                                                 else
                                                     echo "http://127.0.0.1:9998/";
                                                 ?>"
                                                 data-param-2="<?php
                                                 if ($node_info["notify"]["handler"] == "Seals\\Notify\\Http" &&
                                                     isset($node_info["notify"]["params"][1]))
                                                     echo $node_info["notify"]["params"][1];
                                                 else
                                                     echo "author:yuyi,email:297341015@qq.com";
                                                 ?>"
                                                 value="Seals\\Notify\\Http"
                                            <?php if ($node_info["notify"]["handler"] == "Seals\\Notify\\Http") echo "selected"; ?>
                                        >http</option>
                                        <option  data-config-class="rabbitmq-config"
                                                 data-param-1="<?php
                                                 if ($node_info["notify"]["handler"] == "Seals\\Notify\\Rabbitmq")
                                                     echo $node_info["notify"]["params"][0];
                                                 else
                                                     echo "wing-binlog-exchange";
                                                 ?>"
                                                 data-param-2="<?php
                                                 if ($node_info["notify"]["handler"] == "Seals\\Notify\\Rabbitmq" &&
                                                     isset($node_info["notify"]["params"][1]))
                                                     echo $node_info["notify"]["params"][1];
                                                 else
                                                     echo "wing-binlog-queue";
                                                 ?>"
                                                 value="Seals\\Notify\\Rabbitmq"
                                            <?php if ($node_info["notify"]["handler"] == "Seals\\Notify\\Rabbitmq") echo "selected"; ?>
                                        >rabbitmq</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label col-md-3 col-sm-3 col-xs-12">__LANG(Param 1)</label>
                                <div class="col-md-9 col-sm-9 col-xs-12">
                                    <input class="param1 form-control" type="text" value="<?php echo $node_info["notify"]["params"][0]; ?>"/>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label col-md-3 col-sm-3 col-xs-12">__LANG(Param 2)</label>
                                <div class="col-md-9 col-sm-9 col-xs-12">
                                    <input class="param2 form-control" type="text" value="<?php if (isset($node_info["notify"]["params"][1]))
                                        echo $node_info["notify"]["params"][1]; ?>"/>
                                </div>
                            </div>
                            <!--                                <div><span onclick="setNotifyConfig(this)" class="button button-small button-local">更新配置</span></div>-->
                            <div class="ln_solid"></div>
                            <div class="form-group">
                                <div class="col-md-9 col-sm-9 col-xs-12 col-md-offset-3">
                                    <button type="button" onclick="setNotifyConfig(this)" class="btn btn-success">__LANG(Update Configure)</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!--Rabbitmq configure-->
                <div class="x_panel data-target-config rabbitmq-config" style="<?php if ($node_info["notify"]["handler"] != "Seals\\Notify\\Rabbitmq") echo 'display: none;';?>">
                    <div class="x_title">
                        <h2>__LANG(Rabbitmq Configure)</h2>
                        <div class="clearfix"></div>
                    </div>
                    <div class="x_content">
                        <div class="c-item form-horizontal form-label-left">
                            <div class="form-group">
                                <label class="control-label col-md-3 col-sm-3 col-xs-12">__LANG(Host)</label>
                                <div class="col-md-9 col-sm-9 col-xs-12">
                                    <input class="host form-control" type="text" value="<?php echo $node_info["rabbitmq"]["host"]; ?>" />
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="control-label col-md-3 col-sm-3 col-xs-12">__LANG(Port)</label>
                                <div class="col-md-9 col-sm-9 col-xs-12">
                                    <input class="port form-control" type="text" value="<?php echo $node_info["rabbitmq"]["port"]; ?>"/>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label col-md-3 col-sm-3 col-xs-12">__LANG(User)</label>
                                <div class="col-md-9 col-sm-9 col-xs-12">
                                    <input class="user form-control" type="text" value="<?php echo $node_info["rabbitmq"]["user"]; ?>"/>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label col-md-3 col-sm-3 col-xs-12">__LANG(Password)</label>
                                <div class="col-md-9 col-sm-9 col-xs-12">
                                    <input class="password form-control" type="text" value=""/>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label col-md-3 col-sm-3 col-xs-12">Vhost</label>
                                <div class="col-md-9 col-sm-9 col-xs-12">
                                    <input class="vhost form-control" type="text" value="<?php echo $node_info["rabbitmq"]["vhost"]; ?>"/>
                                </div>
                            </div>
                            <!--                            <div><span onclick="setRabbitmqConfig(this)" class="button button-small button-local">更新配置</span></div>-->
                            <div class="ln_solid"></div>
                            <div class="form-group">
                                <div class="col-md-9 col-sm-9 col-xs-12 col-md-offset-3">
                                    <button type="button" onclick="setRabbitmqConfig(this)" class="btn btn-success">__LANG(Update Configure)</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!--Event redis configure-->
                <div class="x_panel data-target-config event-redis-config" style="<?php if ($node_info["notify"]["handler"] != "Seals\\Notify\\Redis") echo 'display: none;'?>">
                    <div class="x_title">
                        <h2>__LANG(Event Redis Configure)</h2>
                        <div class="clearfix"></div>
                    </div>
                    <div class="x_content">
                        <div class="c-item form-horizontal form-label-left">
                            <div class="form-group">
                                <label class="control-label col-md-3 col-sm-3 col-xs-12">__LANG(Host)</label>
                                <div class="col-md-9 col-sm-9 col-xs-12">
                                    <input class="host form-control" type="text" value="<?php echo $node_info["redis_config"]["host"]; ?>"/>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label col-md-3 col-sm-3 col-xs-12">__LANG(Port)</label>
                                <div class="col-md-9 col-sm-9 col-xs-12">
                                    <input class="port form-control" type="text" value="<?php echo $node_info["redis_config"]["port"]; ?>"/>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="control-label col-md-3 col-sm-3 col-xs-12">__LANG(Password)</label>
                                <div class="col-md-9 col-sm-9 col-xs-12">
                                    <input class="password form-control" type="text" value=""/>
                                    <ul class="parsley-errors-list filled" id="parsley-id-5">
                                        <li class="parsley-required">__LANG(you can use :null to set the password as null)</li>
                                    </ul>
                                </div>
                            </div>
                            <!--                            <div><span onclick="setRedisConfig(this)" class="button button-small button-local">更新配置</span></div>-->
                            <div class="ln_solid"></div>
                            <div class="form-group">
                                <div class="col-md-9 col-sm-9 col-xs-12 col-md-offset-3">
                                    <button type="button" onclick="setRedisConfig(this)" class="btn btn-success">__LANG(Update Configure)</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!--Database configure-->
                <div class="x_panel">
                    <div class="x_title">
                        <h2>__LANG(Database Configure)</h2>
                        <div class="clearfix"></div>
                    </div>
                    <div class="x_content">
                        <div class="c-item form-horizontal form-label-left">

                            <div class="form-group">
                                <label class="control-label col-md-3 col-sm-3 col-xs-12">__LANG(Host)</label>
                                <div class="col-md-9 col-sm-9 col-xs-12">

                                    <input class="host form-control" type="text" value="<?php echo $node_info["db_config"]["host"]; ?>"/>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label col-md-3 col-sm-3 col-xs-12">__LANG(Port)</label>
                                <div class="col-md-9 col-sm-9 col-xs-12">
                                    <input class="port form-control" type="text" value="<?php echo $node_info["db_config"]["port"]; ?>"/>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label col-md-3 col-sm-3 col-xs-12">__LANG(User)</label>
                                <div class="col-md-9 col-sm-9 col-xs-12">
                                    <input class="user form-control" type="text" value="<?php echo $node_info["db_config"]["user"]; ?>"/>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label col-md-3 col-sm-3 col-xs-12">__LANG(Password)</label>
                                <div class="col-md-9 col-sm-9 col-xs-12">
                                    <input class="password form-control" type="text" value=""/>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label col-md-3 col-sm-3 col-xs-12">__LANG(Database)</label>
                                <div class="col-md-9 col-sm-9 col-xs-12">

                                    <select class="db_name form-control">
                                        <?php foreach ($databases as $database){
                                            $selected = $node_info["db_config"]["db_name"] == $database ? "selected" : "";
                                            ?>
                                            <option <?php echo $selected; ?> value="<?php echo $database; ?>"><?php echo $database; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <!--            <input class="db_name" type="text"  value="--><?php //echo $node_info["db_config"]["db_name"]; ?><!--"/>-->
                            </div>
                            <!--                        <div><span onclick="setDbConfig(this)" class="button button-small button-local">更新配置</span></div>-->
                            <div class="ln_solid"></div>
                            <div class="form-group">
                                <div class="col-md-9 col-sm-9 col-xs-12 col-md-offset-3">
                                    <button type="button" onclick="setDbConfig(this)" class="btn btn-success">__LANG(Update Configure)</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="col-md-6 col-xs-12">

                <!--Group configure-->
                <div class="x_panel">
                    <div class="x_title">
                        <h2>__LANG(Group Configure)</h2>
                        <div class="clearfix"></div>
                    </div>
                    <div class="x_content">
                        <div class="c-item form-horizontal form-label-left">

                            <div class="form-group">
                                <label class="control-label col-md-3 col-sm-3 col-xs-12">__LANG(Group ID)</label>
                                <div class="col-md-9 col-sm-9 col-xs-12">
                                    <input class="group_id form-control" type="text" value="<?php echo $node_info["zookeeper"]["group_id"]; ?>"/>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label col-md-3 col-sm-3 col-xs-12">__LANG(Host)</label>
                                <div class="col-md-9 col-sm-9 col-xs-12">
                                    <input class="host form-control" type="text" value="<?php echo $node_info["zookeeper"]["host"]; ?>"/>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label col-md-3 col-sm-3 col-xs-12">__LANG(Port)</label>
                                <div class="col-md-9 col-sm-9 col-xs-12">
                                    <input class="port form-control" type="text" value="<?php echo $node_info["zookeeper"]["port"]; ?>"/>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label col-md-3 col-sm-3 col-xs-12">__LANG(Password)</label>
                                <div class="col-md-9 col-sm-9 col-xs-12">
                                    <input class="password form-control" type="text" value=""/>
                                    <ul class="parsley-errors-list filled" id="parsley-id-5">
                                        <li class="parsley-required">__LANG(you can use :null to set the password as null)</li>
                                    </ul>
                                </div>
                            </div>
                            <!--                        <div><span onclick="setZookeeperConfig(this)" class="button button-small button-local">更新配置</span></div>-->
                            <div class="ln_solid"></div>
                            <div class="form-group">
                                <div class="col-md-9 col-sm-9 col-xs-12 col-md-offset-3">
                                    <button type="button" onclick="setZookeeperConfig(this)" class="btn btn-success">__LANG(Update Configure)</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!--Local redis configure-->
                <div class="x_panel">
                    <div class="x_title">
                        <h2>__LANG(Local Redis Configure)</h2>
                        <div class="clearfix"></div>
                    </div>
                    <div class="x_content">
                        <div class="c-item form-horizontal form-label-left">
                            <div class="form-group">
                                <label class="control-label col-md-3 col-sm-3 col-xs-12">__LANG(Host)</label>
                                <div class="col-md-9 col-sm-9 col-xs-12">
                                    <input class="host form-control" type="text" value="<?php echo $node_info["redis_local"]["host"]; ?>" />
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label col-md-3 col-sm-3 col-xs-12">__LANG(Port)</label>
                                <div class="col-md-9 col-sm-9 col-xs-12">
                                    <input class="port form-control" type="text" value="<?php echo $node_info["redis_local"]["port"]; ?>"/>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label col-md-3 col-sm-3 col-xs-12">__LANG(Port)</label>
                                <div class="col-md-9 col-sm-9 col-xs-12">
                                    <input class="password form-control" type="text" value=""/>
                                    <ul class="parsley-errors-list filled" id="parsley-id-5">
                                        <li class="parsley-required">__LANG(you can use :null to set the password as null)</li>
                                    </ul>
                                </div>
                            </div>
                            <!--                                <div><span onclick="setLocalRedisConfig(this)" class="button button-small button-local">更新配置</span></div>-->
                            <div class="ln_solid"></div>
                            <div class="form-group">
                                <div class="col-md-9 col-sm-9 col-xs-12 col-md-offset-3">
                                    <button type="button" onclick="setLocalRedisConfig(this)" class="btn btn-success">__LANG(Update Configure)</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
<script src="js/group.config.js"></script>
<?php include  __DIR__."/include/footer.php";?>
