# アプリケーション名： 模擬案件中級_勤怠管理アプリ
# 環境構築
Dockerビルド
<br>
<br>
　1\. 　git cloneリンク（ターミナルコマンド） git clone https://github.com/takayuki2025/simulation2_1.git  の実行
<br>
　2\. （ターミナルコマンド）cd simulation2_1　の実行。
<br>
　3\. Docker Desktopを立ち上げて（ターミナルコマンド）docker-compose up -d --build　の実行
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
　(PHPコンテナー)  cp .env.example .env　の実行後.envの環境変数の変更(今回は開発用を事前に.env.exampleにAPP_KEY以外は写しておきました。)
<br>
　4\. アプリケーションキーの作成<br>
　　（PHPコンテナー）php artisan key:generate　の実行
<br>
　5\. マイグレーションの実行<br>
　　php artisan migrate　の実行
<br>
　6\. シーディングの実行<br>
　　php artisan db:seed　の実行
<br>
　7\. テスト用のデーターベース作成からPHPUnitテスト実行まで。<br>
　（exitでターミナルに戻ってから）docker-compose exec mysql bash　を実行<br>
　（mysqlコンテナー）mysql -u root -p   の実行後パスワード　root　と入力して実行<br>
　（mysql接続後）CREATE DATABASE coachtech2_test;　を実行 (実行後exitコマンドでターミナルまで戻る)<br>
（ターミナルで　docker-compose exec php bash を実行した後のPHPコンテナーで）vendor/bin/phpunit　を実行してテストをしてください。<br>

<br>
<br>

# 伝えること<br>
- ユーザー情報（管理者・一般ユーザ）、勤怠記録情報のダミーデーターを作成致しました。<br>
　ユーザー情報一覧です。'　'は削除してください。　roleは管理者と一般ユーザーを分けるためのカラムです。<br>
　　１　名前：'川田　隆之'、メールアドレス：'t.principle.k2024@gmail.com'、パスワード：'takayuki'、role：'admin'（管理者）、<br>
　　２　名前：'山田　太郎'、メールアドレス：'taro.y@coachtech.com'、パスワード：'testtest1'、role：'employee'（一般ユーザー）、<br>
　　３　名前：'西　怜奈'、メールアドレス：'reina.n@coachtech.com'、パスワード：'testtest2'、role：'employee'（一般ユーザー）、<br>
　　４　名前：'秋田　朋美'、メールアドレス：'tomomi.a@coachtech.com'、パスワード：'testtest3'、role：'employee'（一般ユーザー）、<br>
　　５　名前：'中西　教夫'、メールアドレス：'norio.n@coachtech.com'、パスワード：'testtest4'、role：'employee'（一般ユーザー）、<br>
　勤怠記録情報はランダムで生成されます。(週休2日制(土日休み)シーダー実行当日より前の31日分、<br>
　修正申請等を入れやすいように平日10％の確率でユーザーごとに出勤なし、休憩一般的な1日３回としてシーダーファイルを作成致しました。)<br><br>

- 専属コーチに承認を得ましたことで、テーブル内のカラムごとのスペースが少しfigmaと違いますが、文字数(名前・メール・備考)が多い場合対策を優先しまして、<br>
　管理者専用ページの日ごとの勤怠一覧、スタッフ一覧では名前は10文字まで(申請一覧では名前8文字まで)、メールアドレス(ドメイン含む)は40文字まで、<br>
　申請一覧(一般ユーザー専用ページも同じ)の備考は9文字までは全て1行で表示できるようにして、名前・備考はそれ以降の文字の場合は... と表示をして、<br>
　ここにしか表示されないメールアドレスの場合は...ではなく2行目以降に折り返すように致しました。<br>
　　(名前は次ページの勤怠詳細ページ、スタッフごとの月単位勤怠ページで広いスペースが取れるのでそこで全て表示されるように折り返して表示されます。)<br>
　　(備考も申請一覧ページの次ページの申請承認ページで全て表示されるようにテーブルのスペース内で折り返しています。)<br><br>

- 専属コーチに承認を得ましたことで、基本ログイン時のフォームリクエストはLoginRequest.phpでバリデーションを設定して、<br>
　データーベースへの確認が必要なバリデーションは、Auth/LoginControllerで実装するように致しました。<br><br>

- 一般ユーザー・管理者ともに未来の日付は修正申請、修正はできないように詳細ボタンを表示されないようにしました。<br><br>

- 一般ユーザーからの修正申請は同じ日は２度修正できないように致しました。<br>
　(一般ユーザー用勤怠詳細ページで、承認待ち：＊承認待ちのため修正はできません。/承認済み：＊この日は一度承認されたので修正できません。<br>
　/修正申請なし：修正ボタン設置。のようにメッセージ内容や送信処理ボタンの設置を状態によって分けています。)<br><br>

- 一般ユーザーの修正申請後は今月の勤怠一覧に戻るようにして申請完了が分かりやすいように上部にセッションメッセージを追加しました。<br><br>

- どのシフト勤務(日勤・夜勤)にも対応できるように修正申請、修正機能も日跨ぎ対応に致しました。



<br>

# スプレットシートの基本設計書にある項目で追加した内容（模擬案件の時だけ掲載）<br><br>
- 画面関係のRoute,Controller<br>
　プロジェクト内のコントローラーは３つで<br>
　・Auth/LoginController：一般ユーザー・管理者ログイン処理<br>
　・UserAttendantManagerController：一般ユーザー処理<br>
　・AdminAttendantManagerController：管理者処理　の３つです。<br><br>

- Viewファイル<br>

- バリデーション関係<br>
　専属コーチに承認を取りましたことで、バリデーションメッセージはテストケース一覧ではなく機能要件と一致するように致しました。<br>
　勤怠修正のバリデーションルールとして機能要件にはなかった、出勤時間等にH:i または HH:ii の形式。の追加。<br>
　出勤時間・退勤時間・休憩開始時間・休憩終了時間のそれぞれの時間が、その項目の後に設定、前に設定する必要があることに関して不適切な設定にならないように、<br>
　それから日跨ぎ勤務対応として出勤後、退勤時間が18時間以内なら日跨ぎ勤務として機能するように、<br>
　ApplicationAndAttendantRequest.phpにwithValidator(追加検証)も追加して実装致しました。<br>

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