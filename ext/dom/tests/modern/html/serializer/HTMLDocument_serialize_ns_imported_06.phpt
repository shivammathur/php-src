--TEST--
Dom\HTMLDocument serialization with an imported namespace node 06
--EXTENSIONS--
dom
--FILE--
<?php

$xml = Dom\XMLDocument::createFromFile(__DIR__.'/sample.xml');
$xml->documentElement->firstElementChild->appendChild($xml->createElementNS('urn:some:ns2', 'child'));
echo $xml->saveXml(), "\n";

echo "--- After clone + import into HTML ---\n";

$html = Dom\HTMLDocument::createFromString('<p>foo</p>', LIBXML_NOERROR);

$p = $html->documentElement->firstElementChild->nextElementSibling->firstElementChild;
$p->appendChild($html->adoptNode($xml->documentElement->firstElementChild->cloneNode(true)));

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
    <child xmlns="urn:some:ns2"/></x>
</container>
--- After clone + import into HTML ---
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<html xmlns="http://www.w3.org/1999/xhtml"><head></head><body><p>foo<x xmlns="urn:some:ns">
        <subcontainer>
            <test xmlns="urn:x:y"/>
            <child2/>
        </subcontainer>
        <subcontainer2>
            <foo xmlns="urn:some:ns"/>
        </subcontainer2>
    <child xmlns="urn:some:ns2"/></x></p></body></html>
<html><head></head><body><p>foo<x>
        <subcontainer>
            <test xmlns="urn:x:y"></test>
            <child2></child2>
        </subcontainer>
        <subcontainer2>
            <foo xmlns="urn:some:ns"></foo>
        </subcontainer2>
    <child></child></x></p></body></html>
