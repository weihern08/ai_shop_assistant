# cPanel 部署说明

## 数据库（已写入 `includes/config.local.php`）

| 项 | 值 |
|----|-----|
| Host | `localhost` |
| Database | `synergy1_weihern_ai_shop_assistant` |
| User | `synergy1_shaoxi` |
| Password | （已配置在 config.local.php） |

## 路径说明

`APP_BASE_PATH` 留空即可：本机与 cPanel 都会自动侦测子目录路径。  
若 CSS 仍错，再在 `config.local.php` 手动设：`putenv('APP_BASE_PATH=/ai_shop_assistant');`

## 上传

1. 解压 zip 到 `public_html/ai_shop_assistant/`（或你的子目录）
2. 确认文件夹可写：`uploads/products/`、`uploads/cache/`

## 导入数据库

1. cPanel → **phpMyAdmin**
2. 选中数据库 `synergy1_weihern_ai_shop_assistant`
3. **Import** → 选择 `sql/cpanel_setup.sql` → Go

导入后会创建：
- 管理员账号
- AI 设置（Gemini）
- 6 个示例商品

## 锁定安装向导

导入 SQL 成功后，在服务器上创建空文件：

```text
includes/install.lock
```

（或访问一次网站后，若仍跳转 install，手动建这个文件即可）

## 管理员登录

- Email: `admin@shop.local`
- Password: `Admin@12345`

登录后请立刻改密码，并在 **AI Settings** 确认 API key。

## HTTPS / 手机扫码

使用你的正式域名 HTTPS 打开网站，例如：

```text
https://你的域名.com/ai_shop_assistant/
```

首页 QR 会自动用当前域名，无需再填局域网 IP。

## AI Connection 失败排查

1. **确认已上传** `includes/config.local.php` 和 `includes/certs/cacert.pem`
2. cPanel → **Select PHP Version** → 启用扩展：`curl`、`openssl`
3. AI Settings 里 Provider 选 **Gemini**，Model 用 `gemini-3.6-flash`，保存后再 Test
4. 若提示 SSL：在 `config.local.php` 打开  
   `putenv('AI_SSL_INSECURE=1');`
5. 若提示无法连接：问主机商是否封锁出站访问 `generativelanguage.googleapis.com`
6. 重新导入或手动确认 `settings` 表里有 `gemini_api_key`

重新测：Admin → AI Settings → **Test AI Connection**（失败信息会更具体）
