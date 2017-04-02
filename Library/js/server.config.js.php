<?php
/**
 * @var NodeSocket $nodeSocket
 * @var NodeSocketCommand $this
 */
?>
module.exports = {
    host : '<?php echo $configs['host']; ?>',
    port : parseInt('<?php echo $configs['port']; ?>'),
    allowedServers : <?php echo json_encode($configs['allowedServers']); ?>,
};
