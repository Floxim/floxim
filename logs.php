<?php

if (!defined('EMERGENCY_CONSOLE') || !EMERGENCY_CONSOLE) {
    die();
}

if (!defined("LOGS_HOME")) {
    define("LOGS_HOME", preg_replace("~\?.*$~", '', $_SERVER['REQUEST_URI']));
}

ob_start();
?>
<style type="text/css">
    html, body {
        margin:0;
        padding:0;
    }
    .header {
        background:#EEE;
        padding:30px;
    }
    .header a {
        display:inline-block;
        margin-right:30px;
    }
    a {
        color:#AAF;
    }
    
    table {
        margin:30px;
        border-collapse:collapse;
    }
    td {
        border:1px solid #CCC;
        padding:15px;
    }
    .fx_debug_entries {
        padding:30px;
    }
    
    .console {
        padding:30px;
    }
    
    .console textarea {
        display:block;
        width:100%;
        margin-bottom:20px;
        height:200px;
        
        font-family:monospace;
        font-size:15px;
    }
</style>
<div class="header">
    <a href="<?= LOGS_HOME ?>">Логи</a> 
    <a href="<?= LOGS_HOME ?>?clear">Очистить</a>
    <a href="<?= LOGS_HOME ?>?console">Консоль</a>
</div>
<?php

if (isset($_GET['console'])) {
    show_console();
} elseif (isset($_GET['clear'])) {
    clear_logs();
} elseif (!isset($_GET['id']) ) {
    show_index();
} else {
    show_log($_GET['id']);
}

$res = ob_get_clean();
echo $res;

function clear_logs() {
    fx::debug()->dropAll();
    header("Location: ".LOGS_HOME);
}

function show_index() {
    $logger = fx::debug();
    $index = $logger->getIndex();

    $lost = $logger->getLost($index);

    $index = fx::collection($index)->concat($lost);
    
    ?>
<table>
    <?php
    foreach ($index as $item) {
        ?>
        <tr>
            <td>
                <a href="?id=<?= $item['id']?>"><?=$item['id']?></a>
                <div style="font-size:12px;"><?= $item['method'] ?> <?= $item['url'] ?></div>
            </td>
            <td>
                <?= fx::date($item['start']) ?>
            </td>
        </tr>
        <?php
    }
    ?>
</table>
    <?php
}

function show_log($id) {
    ?>
    <?php
    $logger = fx::debug();
    $logger->addAssets();
    ?><div class="fx_debug_entries"><?= $logger->showItem($id) ?></div><?php
}

function show_console() {
    $code = isset($_POST['code']) ? $_POST['code'] : '';
    ?>
    <div class="console">
        <form method="post" action="<?= LOGS_HOME ?>?console">
            <textarea name="code"><?= htmlspecialchars($code) ?></textarea>
            <input type="submit" label="Go" />
        </form>
        <?php
        if (!empty($code)) {
            $res = \Floxim\Floxim\Admin\Controller\Console::execute(array('console_text' => $code) );
            echo $res['result'];
        }
        ?>
    </div>
    <?php
}
