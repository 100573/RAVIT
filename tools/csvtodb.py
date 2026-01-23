#!/usr/bin/env python
# -*- coding: utf-8 -*-

import argparse
import pandas as pd
from sqlalchemy import create_engine


def normalize_column_name(name: str) -> str:
    """
    ExcelヘッダーをそのままSQLカラム名に使うと
    スペースや全角文字で怒られることがあるので、
    ざっくり整形しておく。
    必要に応じてロジックを強化してOK。
    """
    name = str(name).strip()
    # 全角・半角スペースをアンダースコアに
    name = name.replace(" ", "_").replace("　", "_")
    # ここで追加の置換をしてもOK（例: 記号を消すなど）
    # name = re.sub(r"[^0-9A-Za-z_]", "", name)
    return name


def main():
    parser = argparse.ArgumentParser(
        description="Excelのヘッダーをカラム名にしてMySQL/MariaDBに取り込むツール"
    )
    parser.add_argument("--excel", required=True, help="入力するExcelファイルパス(.xlsx)")
    parser.add_argument("--sheet", default=0, help="シート名 or シート番号(デフォルト:0)")
    parser.add_argument("--table", required=True, help="書き込み先テーブル名")

    parser.add_argument("--host", default="127.0.0.1", help="DBホスト名 (デフォルト:127.0.0.1)")
    parser.add_argument("--port", type=int, default=3306, help="DBポート (デフォルト:3306)")
    parser.add_argument("--user", required=True, help="DBユーザー名")
    parser.add_argument("--password", required=True, help="DBパスワード")
    parser.add_argument("--db", required=True, help="DB名")

    parser.add_argument(
        "--replace",
        action="store_true",
        help="テーブルを作り直して上書きしたい場合に指定（既存テーブルがあればDROP相当）",
    )

    args = parser.parse_args()

    # 1. Excel読み込み
    print(f"[INFO] Excel読み込み中: {args.excel}")
    df = pd.read_excel(args.excel, sheet_name=args.sheet)

    if df.empty:
        print("[WARN] Excelにデータがありません。処理を終了します。")
        return

    # 2. カラム名を整形（Excelのヘッダー行がそのままdf.columnsに入る想定）
    new_columns = [normalize_column_name(c) for c in df.columns]
    print("[INFO] カラム名変換:")
    for old, new in zip(df.columns, new_columns):
        print(f"  '{old}' -> '{new}'")
    df.columns = new_columns

    # 3. DB接続
    url = (
        f"mysql+pymysql://{args.user}:{args.password}"
        f"@{args.host}:{args.port}/{args.db}?charset=utf8mb4"
    )
    print(f"[INFO] DB接続中: {url}")
    engine = create_engine(url, echo=False)

    # 4. テーブルへの書き込み
    if_exists_mode = "replace" if args.replace else "append"
    print(f"[INFO] テーブル '{args.table}' へ if_exists='{if_exists_mode}' で書き込み開始...")
    # NOTE:
    # テーブルが存在しない場合 → 自動でCREATE TABLEしてくれる
    # テーブルが存在する場合
    #   replace -> ドロップ&作り直し
    #   append  -> 既存テーブルにINSERT
    df.to_sql(args.table, con=engine, if_exists=if_exists_mode, index=False)

    print("[INFO] インポート完了")


if __name__ == "__main__":
    main()
