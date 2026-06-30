--TEST--
Bug #48725 (Support for flushing in zlib stream)
--EXTENSIONS--
zlib
--FILE--
<?php
$text = str_repeat('0123456789abcdef', 1000);

$temp = fopen('php://temp', 'r+');
stream_filter_append($temp, 'zlib.deflate', STREAM_FILTER_WRITE);
fwrite($temp, $text);

rewind($temp);

var_dump(bin2hex(stream_get_contents($temp)));
var_dump(ftell($temp));

fclose($temp);
?>
--EXPECTREGEX--
(?:string\(138\) "ecc7c901c0100000b09594bac641d97f840e22f9253c31bdb9d4d6c75cdf3ec1ddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddaffc0f0000ffff"\nint\(69\)|string\(274\) "ecd64b15c0200c04404b14281f3950a87f0908618eb9e625bb139e98f25b6aeb637e6bffc16c1feec13fc80379a80ff4210ff0000ff0000ff0000ff0000ff0000ff0000ff0000ff0000ff0000ff0000ff0000ff0000ff0000ff0000ff0000ff0000ff0000ff0000ff0000ff0000ff0000ff0000ff0000ff0000ff0000ff0000fcc1bfbf0000000ffff"\nint\(137\))
