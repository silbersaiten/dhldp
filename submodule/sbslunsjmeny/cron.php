<?php
/**
 * CLI entrypoint for SBS XML product import cron.
 */

// 1)
$_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'lunsj.no';
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'];
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/index.php?fc=module&module=sbslunsjmeny&controller=cron';
$_SERVER['HTTPS'] = $_SERVER['HTTPS'] ?? 'on';
$_SERVER['SERVER_PORT'] = $_SERVER['SERVER_PORT'] ?? 443;

// 2)
$_GET['fc'] = 'module';
$_GET['module'] = 'sbslunsjmeny';
$_GET['controller'] = 'cron';

// 3)
require_once __DIR__ . '/../../index.php';
