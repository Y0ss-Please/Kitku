<?php

require_once 'kitku/kitku.php';

if (!empty($_FILES) || !empty($_POST)) {

    /*
    echo "_FILES\r\n";
    $image = new KitkuImageUploader ($_FILES['the-file']['tmp_name'], 'test');
    var_dump($image);
    echo "\r\n";
    echo "\r\n";
    echo "\r\n";
    */

    echo "_POST\r\n";
    $image = new KitkuImageUploader ($_POST['base64'], 'images/', 'filename', 'base64');
    echo $_POST['base64'];
    echo "\r\n";
    echo "\r\n";
    echo "\r\n";
    var_dump($image);

    exit();
}

?>

<form id='test' onsubmit="return new_post(event);">
    <input type="file" name="the-file"></input>
    <br />
    <input type="submit"></input>
</form>

<script>

function new_post(e) {
    e.preventDefault();

    console.clear();
    
    const form = document.forms['test'];
    const fd = new FormData(form);
    fd.append('base64',
    'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAABHUlEQVRYhcWXTQ6CMBBG22pi4kYTfxYuuIWJ8SyuvAUbFmy4hStvwI24gwsTY02VkkKUTJlvcDYF2vBeB5hSba1VLtbLrXbt7Hp5KsG4n84mvHvrRBr+jaFXi8287rj5i0leiMCrLG2OfSbMWPDuvX0m9K4sbR88tKYIUsaHY0zfwNjown9FyDKDaT2zipkURGAoHCLAgbMFuHCWAALOEkDAYQKcT5gtwK0f03+BfURngFrtxATQEtDPcFQBlIRIKRYVoPwziAqgJQY/ApQE6x1ASIiU4hgJscWIKiG6HFMkGgFEUaEuUCFLH/ZHXWVps12S3Jh04UleTEx9AM0EFe5alwEPd5l4iNGDSPLC/Ye8s956CeuOMeCfUEq9AKt8dhP3psrTAAAAAElFTkSuQmCC'
    );

    const xhttp = new XMLHttpRequest();
            xhttp.open('POST', 'test.php', true);
            xhttp.send(fd);

    xhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200){
            const result = this.responseText;
            console.log(result);
        }
    }
}

</script>