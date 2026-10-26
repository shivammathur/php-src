--TEST--
openssl_spki_export_challenge() preserves empty and embedded-NUL challenges
--EXTENSIONS--
openssl
--FILE--
<?php
$key = openssl_pkey_get_private('file://' . __DIR__ . '/private_rsa_2048.key');
foreach (['', "\0", "before\0after", 'challenge'] as $challenge) {
    $spki = openssl_spki_new($key, $challenge, OPENSSL_ALGO_SHA256);
    $spki = substr($spki, strlen('SPKAC='));
    var_dump(openssl_spki_verify($spki));
    var_dump(openssl_spki_export_challenge($spki) === $challenge);
}
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
