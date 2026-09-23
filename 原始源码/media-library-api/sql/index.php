<?php
/**
 * 目录拒访（index.php）
 * ==================================================================
 * 本目录里放的是配置 / SQL / 源码 / 定时脚本，**不允许从公网访问**。
 * 这里放一个"目录索引"文件，任何对本目录的目录级访问都会直接 404。
 *
 * 配合：
 *   1) core/Guard.php —— 入口守卫，把 /config/ /sql/ /core/ ... 路径一律 404；
 *   2) 每个内部 .php 文件顶部的 ml_guard_shield() —— 直接请求该文件也 404；
 *   3) DEPLOY.md「安全加固」里的 Nginx 规则（最彻底）。
 *
 * 注意：本文件是**拒访页**，不是入口，因此不引入任何其它文件。
 */

http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">'
   . '<meta name="viewport" content="width=device-width,initial-scale=1">'
   . '<title>404 Not Found</title></head>'
   . '<body style="margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;'
   . "font-family:-apple-system,'Segoe UI','Microsoft YaHei',sans-serif;background:#f8fafc;color:#64748b\">"
   . '<div style="text-align:center"><div style="font-size:58px;font-weight:700;color:#cbd5e1">404</div>'
   . '<div style="margin-top:6px;font-size:14px">Not Found</div></div></body></html>';
exit;
