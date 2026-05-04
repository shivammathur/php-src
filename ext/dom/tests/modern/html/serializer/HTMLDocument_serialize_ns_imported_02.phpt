--TEST--
Dom\HTMLDocument serialization with an imported namespace node 02
--EXTENSIONS--
dom
--FILE--
<?php

$xml = Dom\XMLDocument::createFromFile(__DIR__.'/sample.xml');
$xml->documentElement->appendChild($xml->createElementNS('urn:some:ns2', 'child'));
echo $xml->saveXml(), "\n";

echo "--- After import into HTML ---\n";

$html = Dom\HTMLDocument::createFromString('<p>foo</p>', LIBXML_NOERROR);

$p = $html->documentElement->firstChild->nextSibling->firstChild;
$p->appendChild($html->importNode($xml->documentElement, true));

echo $html->saveXml(), "\n";
echo $html->saveHtml(), "\n";

?>
--EXPECT--
<?xml version="1.0" encoding="UTF-8"?>
<container xmlns="urn:some:ns" xmlns:bar="urn:another:ns">
    <x>
        <subcontainer>
            <test xmlns="urn:x:y"/>
            <child2/>
        </subcontainer>
        <subcontainer2>
            <foo xmlns="urn:some:ns"/>
        </subcontainer2>
    </x>
<child xmlns="urn:some:ns2"/></container>
--- After import into HTML ---
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<html xmlns="http://www.w3.org/1999/xhtml"><head></head><body><p>foo<container xmlns="urn:some:ns" xmlns:bar="urn:another:ns">
    <x>
        <subcontainer>
            <test xmlns="urn:x:y"/>
            <child2/>
        </subcontainer>
        <subcontainer2>
            <foo xmlns="urn:some:ns"/>
        </subcontainer2>
    </x>
<child xmlns="urn:some:ns2"/></container></p></body></html>
<html><head></head><body><p>foo<container xmlns="urn:some:ns" xmlns:bar="urn:another:ns">
    <x>
        <subcontainer>
            <test xmlns="urn:x:y"></test>
            <child2></child2>
        </subcontainer>
        <subcontainer2>
            <foo xmlns="urn:some:ns"></foo>
        </subcontainer2>
    </x>
<child></child></container></p></body></html>
