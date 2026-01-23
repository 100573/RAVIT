import pandas as pd
import random
import numpy as np

random.seed(42)

# ===== パラメータ =====
NUM_WORKERS = 200
LINES = [1, 2, 3, 4, 5]
PROCESSES = [f"M{i}" for i in range(1, 11)]
WORK_HOURS = 8  # 1人あたり8時間勤務
DAYS = ["Day1", "Day2"]

# ===== テスト従業員データ生成 =====
workers = []
for i in range(1, NUM_WORKERS + 1):
    line = random.choice(LINES)  # 所属ライン
    skills = random.sample(PROCESSES, random.randint(2, 5))  # 2～5工程スキルあり
    workers.append({
        "WorkerID": f"W{i:03d}",
        "HomeLine": line,
        "Skills": ",".join(skills)
    })

workers_df = pd.DataFrame(workers)

# ===== 日ごとの需要（ライン×工程×人数）を生成 =====
demand = []
for day in DAYS:
    for line in LINES:
        for process in PROCESSES:
            need = random.randint(2, 6)  # 工程ごとに2～6人必要
            demand.append({
                "Day": day,
                "Line": line,
                "Process": process,
                "Need": need
            })
demand_df = pd.DataFrame(demand)

# ===== 割付処理 =====
assignments = []

for day in DAYS:
    daily_demand = demand_df[demand_df["Day"] == day]

    for _, row in daily_demand.iterrows():
        line = row["Line"]
        process = row["Process"]
        need = row["Need"]

        # 候補者（スキル持ち）
        candidates = workers_df[workers_df["Skills"].apply(lambda s: process in s)]
        chosen = random.sample(list(candidates["WorkerID"]), min(len(candidates), need))

        # 割付結果を記録
        for worker in chosen:
            home_line = workers_df.loc[workers_df["WorkerID"] == worker, "HomeLine"].values[0]
            assignments.append({
                "Day": day,
                "Line": line,
                "Process": process,
                "Worker": worker,
                "HomeLine": home_line,
                "Hours": WORK_HOURS / need  # 工程の必要人数で等分
            })

assignments_df = pd.DataFrame(assignments)

# ===== 工数貸借の集計 =====
# 自分のライン以外で働いた時間を「貸した/借りた」とみなす
flow = []
for day in DAYS:
    daily_assign = assignments_df[assignments_df["Day"] == day]

    for line in LINES:
        own = daily_assign[daily_assign["HomeLine"] == line]
        work = daily_assign[daily_assign["Line"] == line]

        # 「貸した」= 所属ラインの人が他ラインで働いた時間
        lent = own[own["Line"] != own["HomeLine"]]["Hours"].sum()

        # 「借りた」= 他ライン所属の人が自ラインで働いた時間
        borrowed = work[work["Line"] != work["HomeLine"]]["Hours"].sum()

        flow.append({
            "Day": day,
            "Line": line,
            "LentHours": round(lent, 2),
            "BorrowedHours": round(borrowed, 2),
            "Net": round(borrowed - lent, 2)
        })

flow_df = pd.DataFrame(flow)

# ===== Excelに出力 =====
with pd.ExcelWriter("shift_testdata.xlsx") as writer:
    workers_df.to_excel(writer, sheet_name="Workers", index=False)
    demand_df.to_excel(writer, sheet_name="Demand", index=False)
    assignments_df.to_excel(writer, sheet_name="Assignments", index=False)
    flow_df.to_excel(writer, sheet_name="Line_Flow", index=False)

print("✅ テストデータを shift_testdata.xlsx に出力しました")
