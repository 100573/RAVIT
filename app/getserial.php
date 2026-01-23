<?php

function getSerial($fno, $cate)
{
    // VISTA情報を取得
    $sql = "SELECT ID FROM I_PARTS_CB WHERE (FNO='";
    $sql = $sql . $fno;
    $sql = $sql . "' AND CATE_NAME='";
    $sql = $sql . $cate;
    $sql = $sql . "')";

    // echo $sql;

    //データベース接続
    $conn = oci_connect('vista', 'vision', 'vista_asy', 'AL32UTF8');
    if (!$conn) {
        $e = oci_error();
        trigger_error(htmlentities($e['message'], ENT_QUOTES), E_USER_ERROR);
    }
    $stid = oci_parse($conn, $sql);
    oci_execute($stid);
    $nrows = oci_fetch_all($stid, $data);
    oci_free_statement($stid);

    $id = $data["ID"][0];
    return $id;
}
