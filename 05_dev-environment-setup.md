# 開発環境セットアップ: WSL2 Ubuntu + VSCode + Claude Code + GitHub + ConoHa + Cloudinary

## 1. WSL2 Ubuntu（Windows11）
```powershell
wsl --install -d Ubuntu
```

## 2. Ubuntu内の初期セットアップ
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y git curl
```

## 3. Claude Codeインストール（ネイティブインストーラ）
```bash
curl -fsSL https://claude.ai/install.sh | bash
source ~/.bashrc
claude --version
claude doctor
```

## 4. VSCode連携
- 拡張機能「WSL」をインストール
- `WSL: Connect to WSL` でUbuntuに接続
- 統合ターミナルはUbuntu bashになる

## 5. プロジェクト作成＋GitHub連携
ローカル（WSL）のプロジェクトパスは `/var/www/html/usedcar-checker` に統一する。
```bash
cd /var/www/html/usedcar-checker
git init
echo "# usedcar-checker" > README.md
echo ".env" > .gitignore
git add . && git commit -m "初期コミット"
git remote add origin https://github.com/<ユーザー名>/usedcar-checker.git
git branch -M main
git push -u origin main
```

## 6. Claude Codeでの開発
プロジェクト直下で `claude` と実行して対話開始。`CLAUDE.md` にスタック情報（PHP/SQLite等）を書いておくと毎回説明不要。

## 7. Cloudinary連携
`.env`（gitignore対象）に認証情報を記載。PHPの場合:
```bash
composer require cloudinary/cloudinary_php
```

## 8. ConoHaサーバーへのデプロイ（push→pull）
サーバー側のパスもローカルと同じ `/var/www/html/usedcar-checker` に統一する。

初回のみサーバー側でclone:
```bash
ssh <ユーザー>@<ConoHaのIP>
cd /var/www/html
git clone https://github.com/<ユーザー名>/usedcar-checker.git usedcar-checker
```
サーバー用`.env`は別途手動配置（gitに含めない）。

以降の通常フロー:
```bash
# ローカル
git add . && git commit -m "変更内容" && git push

# サーバー（SSH接続後）
cd /var/www/html/usedcar-checker && git pull
```
