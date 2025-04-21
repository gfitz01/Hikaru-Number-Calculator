<?php 

$command = escapeshellcmd('/usr/bin/env python3 /Users/lisadelprete/Desktop/Hikaru-Number-Calculator-main/played-hikaru.py');
$output = shell_exec($command);
echo "<pre>$output</pre>";

?>