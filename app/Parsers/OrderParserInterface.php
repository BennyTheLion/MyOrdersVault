<?php
namespace MyOrdersVault\Parsers;

interface OrderParserInterface {
    public function parse($emailContent, $subject);
    public function supports($emailFrom);
}