# アプリケーション名： 模擬案件中級_勤怠管理アプリ
# 環境構築
Dockerビルド
<br>
<br>
　1\. 　git cloneリンク（ターミナルコマンド） git clone https://github.com/takayuki2025/simulation2_1.git  の実行
<br>
　2\. （ターミナルコマンド）cd simulation2_1　の実行。
<br>

　5\. Docker Desktopを立ち上げて（ターミナルコマンド）docker-compose up -d --build　の実行
<br>
　
  <br>
laravel環境構築
<br>
<br>
　1\. （ターミナルコマンド）docker-compose exec php bash　の実行
<br>
　2\. （PHPコンテナー）composer install　の実行
<br>
　3\. 　env.exampleファイルから.envを作成し、.envファイルの環境変数を変更<br>
　(PHPコンテナー)  cp .env.example .env　の実行後.envの環境変数の変更<br>




<br>
<br>
　4\. アプリケーションキーの作成<br>
　　（PHPコンテナー）php artisan key:generate
<br>
　5\. マイグレーションの実行<br>
　　php artisan migrate
<br>
　6\. シーディングの実行<br>
　　php artisan db:seed
<br>
　7\. テスト用のデーターベース作成からPHPUnitテスト実行まで。<br>
　（exitでターミナルに戻ってから）docker-compose exec mysql bash　を実行<br>
　（mysqlコンテナー）mysql -u root -p   の実行後パスワード　root　と入力して実行<br>
　（mysql接続後）CREATE DATABASE coachtech1_test;　を実行 (実行後exitコマンドでターミナルまで戻る)<br>
（ターミナルで　docker-compose exec php bash を実行した後のPHPコンテナーで）php artisan test　を実行してテストをしてください。<br>

<br>

# 伝えること<br>

<br>

# スプレットシートの基本設計書にある項目で追加した内容（模擬案件の時だけ掲載）<br><br>
- 画面関係のRoute,Controller<br>

- Viewファイル<br>

- バリデーション関係<br>


<br>
<br>

# 今後開発品質の高い効率の良いWEB開発をしていく上でのまとめ（模擬案件の時だけ掲載）<br>


<br>

# ER図<br>
<img width="1920" height="1080" alt="Image" src="https://github.com/user-attachments/assets/5a23e7bd-f449-4065-b39b-1955fdf46d1f" />
<br>

# 使用技術<br>
  - PHP 8.4.12
  - Laravel 12.30.1 (fortify 1.30)
  - MySql 8.4.6
  - nginx 1.28.0
<br>

# URL<br>
  - ユーザー登録： http://localhost/register/
  - 管理者用ログイン： http://localhost/admin/login
  - phpMyAdmin:http://localhost:8080/
  - meilhog： http://localhost:8025/