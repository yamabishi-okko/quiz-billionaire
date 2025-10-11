<script setup lang="ts">
import { ref, watch } from 'vue'

type QuestionRow = { id:number; title:string; created_at:string; updated_at:string; choices_count:number }

const tab = ref<'play' | 'create' | 'manage'>('play')

// ------- 一覧（管理） -------
const list = ref<QuestionRow[]>([])
const total = ref(0)
const limit = ref(20)
const offset = ref(0)
const loadingList = ref(false)
const listError = ref<string | null>(null)

async function fetchQuestions() {
  loadingList.value = true
  listError.value = null
  try {
    const r = await fetch(`${API}/api/questions?limit=${limit.value}&offset=${offset.value}`)
    if (!r.ok) throw new Error(`failed: ${r.status}`)
    const data = await r.json()
    list.value = data.items
    total.value = data.total
  } catch (e:any) {
    listError.value = e?.message ?? '一覧の取得に失敗しました'
  } finally {
    loadingList.value = false
  }
}

async function deleteQuestion(id:number) {
  if (!confirm(`本当に削除しますか？ (ID: ${id})`)) return
  try {
    const r = await fetch(`${API}/api/questions/${id}`, { method: 'DELETE' })
    if (!r.ok) throw new Error(`failed: ${r.status}`)
    // 再取得
    await fetchQuestions()
  } catch (e:any) {
    alert(e?.message ?? '削除に失敗しました')
  }
}

/** 型 */
type Question = { id: number; title: string }
type Choice   = { id?: number; body: string; is_correct: boolean }
type RandomResponse = { question: Question; choices: { id:number; body:string }[] }
type CheckResponse  = { correct: boolean; answer?: { id:number; body:string } }

/** APIベースURL（.env.development の VITE_API_BASE を利用） */
const API = import.meta.env.VITE_API_BASE as string

/* ===================== プレイ（出題） ===================== */
const loading = ref(false)
const data = ref<RandomResponse | null>(null)
const selectedChoiceId = ref<number | null>(null)
const result = ref<CheckResponse | null>(null)
const errorMsg = ref<string | null>(null)

async function fetchRandom() {
  loading.value = true
  result.value = null
  selectedChoiceId.value = null
  errorMsg.value = null
  try {
    const r = await fetch(`${API}/api/questions/random`)
    if (!r.ok) throw new Error(`failed: ${r.status}`)
    data.value = await r.json()
  } catch (e:any) {
    errorMsg.value = e?.message ?? '取得に失敗しました'
  } finally {
    loading.value = false
  }
}

async function checkAnswer() {
  if (!data.value || selectedChoiceId.value == null) return
  errorMsg.value = null
  try {
    const r = await fetch(`${API}/api/answers/check`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        question_id: data.value.question.id,
        choice_id: selectedChoiceId.value
      })
    })
    if (!r.ok) throw new Error(`failed: ${r.status}`)
    result.value = await r.json()
  } catch (e:any) {
    errorMsg.value = e?.message ?? '判定に失敗しました'
  }
}

/* ===================== 作問（登録） ===================== */
const title = ref('')
const choices = ref<Choice[]>([
  { body: '', is_correct: true  },
  { body: '', is_correct: false },
  { body: '', is_correct: false },
  { body: '', is_correct: false },
])
const creating = ref(false)
const createMsg = ref<string | null>(null)

function markCorrect(idx:number) {
  choices.value = choices.value.map((c,i)=> ({ ...c, is_correct: i===idx }))
}

function validateCreate(): string | null {
  if (title.value.trim() === '') return 'タイトルは必須です'
  if (choices.value.length !== 4) return '選択肢は4件です'
  for (let i=0;i<4;i++){
    if (choices.value[i].body.trim() === '') return `選択肢${i+1}は必須です`
  }
  const correct = choices.value.filter(c=>c.is_correct).length
  if (correct !== 1) return '正解は1つだけにしてください'
  return null
}

async function submitCreate() {
  createMsg.value = null
  const err = validateCreate()
  if (err) { createMsg.value = err; return }

  creating.value = true
  try {
    const r = await fetch(`${API}/api/questions/create`, {
      method: 'POST',
      headers: { 'Content-Type':'application/json' },
      body: JSON.stringify({ title: title.value, choices: choices.value })
    })
    if (!r.ok) throw new Error(`API ${r.status}`)
    const data = await r.json()
    createMsg.value = `作成しました（ID: ${data.id}）`

    // クリアしてもう一問作れるように
    title.value = ''
    choices.value = [
      { body: '', is_correct: true  },
      { body: '', is_correct: false },
      { body: '', is_correct: false },
      { body: '', is_correct: false },
    ]
  } catch (e:any) {
    createMsg.value = e?.message ?? '作成に失敗しました'
  } finally {
    creating.value = false
  }
}

watch(tab, (v) => {
  if (v === 'manage') fetchQuestions()
})
</script>

<template>
  <div class="millionaire">
  <!-- 画面サイズに依存しない中央フレーム -->
    <div class="frame">
      <!-- 左上 HUD -->
      <div class="hud-left">
        <div class="trial">T R I A L</div>
        <div class="money-badge"><span class="money-text">¥100,000,000,000</span></div>
        <nav class="lifelines">
          <button :class="{active: tab==='play'}"   @click="tab='play'">プレイ</button>
          <button :class="{active: tab==='create'}" @click="tab='create'">作問</button>
          <button :class="{active: tab==='manage'}" @click="tab='manage'">管理</button>
        </nav>
      </div>

      <!-- ======= プレイ ======= -->
      <section v-if="tab==='play'" class="stage bottom">
        <div class="row ctr">
          <button class="primary" @click="fetchRandom" :disabled="loading">
            {{ loading ? '読み込み中…' : 'ランダムで1問' }}
          </button>
        </div>

        <div v-if="data" class="board">
          <div class="prompt">
            Q{{ data.question.id }}．{{ data.question.title }}
          </div>

          <ul class="answers">
            <li v-for="(c,i) in data.choices" :key="c.id">
              <!-- ダイヤ形ボタン -->
              <button
                class="diamond"
                :class="{ selected: selectedChoiceId===c.id }"
                @click="selectedChoiceId = c.id"
              >
                <span class="label">{{ 'ABCD'[i] }}</span>
                <span class="text">{{ c.body }}</span>
              </button>
            </li>
          </ul>

          <div class="row ctr">
            <button class="primary" @click="checkAnswer" :disabled="selectedChoiceId==null">
              ファイナルアンサー？
            </button>
          </div>

          <p v-if="result" class="result">
            <strong v-if="result.correct" class="ok">正解！</strong>
            <strong v-else class="ng">残念…</strong>
            <span v-if="result.answer">&nbsp;（正解：{{ result.answer.body }}）</span>
          </p>
        </div>

        <p v-if="errorMsg" class="error">{{ errorMsg }}</p>
        <p class="hint">まずは右上のボタンで「プレイ」を選び、「ランダムで1問」を押してね。</p>
      </section>

      <!-- ======= 作問 ======= -->
      <section v-else-if="tab==='create'" class="panel">
        <h2>新しい問題を作る</h2>

        <label class="block">
          <span>タイトル</span>
          <input v-model="title" placeholder="例）HTTPはどの階層のプロトコル？">
        </label>

        <div class="choices-form">
          <div v-for="(c,i) in choices" :key="i" class="choice-row">
            <input v-model="c.body" :placeholder="`選択肢${i+1}`">
            <label class="radio">
              <input type="radio" name="correct" :checked="c.is_correct" @change="markCorrect(i)">
              正解
            </label>
          </div>
        </div>

        <div class="row ctr">
          <button class="primary" @click="submitCreate" :disabled="creating">登録する</button>
        </div>
        <p v-if="createMsg" class="msg">{{ createMsg }}</p>
      </section>

      <!-- ======= 管理（一覧＋削除） ======= -->
      <section v-else class="panel">
        <div class="panel-head">
          <h2>問題一覧</h2>
          <div class="tools">
            <button @click="fetchQuestions" :disabled="loadingList">再読み込み</button>
            <span class="muted">{{ total }}件</span>
          </div>
        </div>

        <p v-if="listError" class="error">{{ listError }}</p>

        <div class="table-wrap" v-if="list.length">
          <table class="tbl">
            <thead>
              <tr>
                <th>ID</th><th>タイトル</th><th>選択肢数</th><th>作成日時</th><th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in list" :key="row.id">
                <td>{{ row.id }}</td>
                <td>{{ row.title }}</td>
                <td>{{ row.choices_count }}</td>
                <td>{{ new Date(row.created_at).toLocaleString() }}</td>
                <td><button class="danger" @click="deleteQuestion(row.id)">削除</button></td>
              </tr>
            </tbody>
          </table>
        </div>

        <p v-else class="hint">まだ問題がありません。</p>
      </section>
    </div>
  </div>
</template>

<style>
/* 背景（固定） */
html, body, #app { height: 100%; }
body{
  background: url('/src/assets/mc2.png') center/cover no-repeat fixed;
}
/* ほんのり暗く＆ビネット */
body::before{
  content:""; position: fixed; inset:0;
  background:
    radial-gradient(ellipse at center, rgba(8,12,30,.1) 0%, rgba(0,0,0,.1) 70%),
    rgba(0,0,0,0);
  pointer-events:none;
}
.frame{
  /* 1120px を上限に、画面が小さいときは 92vw まで縮む */
  width: min(1120px, 92vw);
  margin: 0 auto;             /* 画面中央に配置 */
  min-height: 100vh;
  position: relative;         /* 内側の absolute の基準にする */
}
</style>

<style scoped>
/* ========== 全体 ========== */
.millionaire{
  min-height: 100vh;
  /* ← 背景は body に任せるので透明に */
  background: transparent;
  color:#e7ebff;
  padding: 24px;
  position: relative;
  font-family: system-ui,-apple-system,Roboto;
}
.brand{
  position:absolute; left:20px; top:12px;
  font-size:18px; letter-spacing:.03em; opacity:.9;
  mix-blend-mode: screen; /* 背景に馴染ませる */
}

/* ========== 右上HUD ========== */
.hud-right{
  position: absolute; top:10px; right:16px;
  display:flex; align-items:center; gap:10px;
  z-index: 10;
}
.hud-left{
  position: absolute; top:12px; left:8px;
  display:flex; flex-direction:column; align-items:center;
  gap:10px; z-index:10;
}
.trial{
  letter-spacing:.55em; color:#9fb6ff; font-weight:700;
  text-shadow:0 0 8px rgba(80,120,255,.55);
  font-size: 24px
}
/* 賞金バッジ（ダイヤ） */
.money-badge{
  position: relative; height: 44px;
  /* 横に広がり過ぎないよう適度に制限 */
  width: clamp(280px, 60%, 520px);
  padding: 0 0px;
  display:flex; align-items:center; justify-content:center;
  background: linear-gradient(180deg,#2b2f55,#1f2446);
  border:1px solid #5a6bff33; border-radius: 12px;
  box-shadow: inset 0 1px 0 rgba(255,255,255,.08), 0 10px 28px rgba(0,0,0,.35);
}
.money-badge::before,.money-badge::after{
  content:""; position:absolute; top:50%; width:26px; height:26px;
  background: linear-gradient(180deg,#3a3f7a,#20264f);
  border:1px solid #5a6bff55;
  transform: translateY(-50%) rotate(45deg);
  box-shadow: inset 0 1px 0 rgba(255,255,255,.07);
}
.money-badge::before{ left:-13px; border-right:none; }
.money-badge::after { right:-13px; border-left:none;  }
.money-text{
  font-weight:800;
  background: linear-gradient(180deg,#ffe9a3,#ffd36a 40%,#f2b93a 70%,#e3a216);
  -webkit-background-clip:text; background-clip:text; color:transparent;
  text-shadow: 0 0 12px rgba(255,220,120,.45), 0 0 30px rgba(255,200,90,.25);
  letter-spacing:.02em; filter: drop-shadow(0 2px 0 rgba(0,0,0,.35));
  font-size: 24px;
}
/* 右上の小タブ */
/* .tabs.compact{ display:flex; gap:8px; }
.tabs.compact button{
  padding:8px 12px; border-radius:10px; font-size:13px;
  background:#0f1a3a; color:#e7ebff; border:1px solid #2a3f82;
}
.tabs.compact button.active{ background:#1a2b5e; border-color:#4b66c6; } */
/* === 楕円のボタン（耳なし・横並び） === */
.lifelines{
  display:flex; gap:12px; margin-top:6px; padding-left:4px;
}
.lifelines button{
  --ring:#8ad3ff;
  height:36px; padding:0 12px;
  display:inline-flex; align-items:center; justify-content:center;
  border-radius:999px; font-weight:700; letter-spacing:.04em;
  background: rgba(8,12,30,.52);
  color:#d9ecff; border:2px solid var(--ring);
  box-shadow:
    0 0 0 2px rgba(0,0,0,.55) inset,
    0 0 16px rgba(70,170,255,.35),
    0 8px 22px rgba(0,0,0,.35);
  backdrop-filter: blur(3px);
  transition: transform .06s ease, filter .2s ease, box-shadow .2s ease;
}
.lifelines button:hover{
  filter: brightness(1.12);
  box-shadow:
    0 0 0 2px rgba(0,0,0,.55) inset,
    0 0 20px rgba(100,190,255,.55),
    0 10px 26px rgba(0,0,0,.4);
}
.lifelines button.active{
  background: #0b1738;
  box-shadow: 0 0 0 2px rgba(0,0,0,.6) inset, 0 0 24px rgba(120,210,255,.55);
}

/* ========== プレイ面 ========== */
.stage{
    margin: 120px auto 40px;
    width: 100%;
}
.row{ margin:14px 0; }
.row.ctr{ display:flex; justify-content:center; }
.primary{
  background:linear-gradient(180deg,#1a56ff,#0d2ebd);
  border:1px solid #4d73ff; color:#fff; padding:10px 16px; border-radius:12px;
  box-shadow:0 6px 18px rgba(23,70,255,.25);
}

/* ⬇⬇ ここが“囲いを消す”ポイント ⬇⬇ */
/* 盤面コンテナの背景/枠をなくして透過 */
.board{ background: transparent; border: none; padding: 0; }

/* 問題文帯（これは残す＝青い帯） */
.prompt{
  /* 問題帯もフレーム幅に合わせて伸び過ぎない */
  max-width: 980px;
  margin: 0 auto 18px;
  text-align:center; font-weight:800; font-size:22px; letter-spacing:.02em;
  /* background: linear-gradient(180deg,#1f3f9c,#122a70); */
  background: #000;
  border:1px solid #3a57b7; color:#ffffff; padding:14px 18px; border-radius:12px;
  box-shadow:0 8px 24px rgba(19,40,120,.35) inset, 0 8px 28px rgba(0,0,0,.25);
  margin-bottom: 18px;
}

/* ダイヤの選択肢（そのまま残す） */
.answers{
  list-style:none; padding:0; margin:0 0 10px;
  max-width: 980px;
  display:grid; grid-template-columns:1fr 1fr; gap:18px;
}
.answers li{ display:flex; justify-content:center; }

.diamond{
  --h: 54px;
  position:relative; min-width: 320px; height: var(--h);
  padding:0 64px 0 72px;
  color:#e9f0ff; font-weight:700; letter-spacing:.02em;
  background: linear-gradient(180deg,#1a2f72,#0e1f4e);
  border:1px solid #3a57b7;
  clip-path: polygon(6% 0, 94% 0, 100% 50%, 94% 100%, 6% 100%, 0 50%);
  box-shadow: 0 6px 20px rgba(0,0,0,.35), 0 0 0 2px rgba(28,52,130,.4) inset, 0 14px 40px rgba(14,31,78,.35) inset;
}
.diamond:hover{ filter: brightness(1.08); }
.diamond.selected{
  background: linear-gradient(180deg,#c89213,#8a5d05);
  border-color:#ffd164; color:#0b0b0b;
  box-shadow: 0 8px 26px rgba(255,206,77,.35), 0 0 0 2px rgba(255,222,140,.45) inset;
}
/* ABCDの小ダイヤ */
.diamond .label{
  position:absolute; left:10px; top:50%; transform:translateY(-50%);
  width:44px; height:34px; display:grid; place-items:center;
  background: linear-gradient(180deg,#203879,#132a62);
  border:1px solid #3c59b8; clip-path: polygon(8% 0, 92% 0, 100% 50%, 92% 100%, 8% 100%, 0 50%);
  box-shadow: 0 0 0 2px rgba(28,52,130,.45) inset;
}
.diamond .text{ white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

.error{ color:#ff8a8a; } .hint{ color:#b7c6ec; }

/* ========== 作問/管理（枠を消して透過） ========== */
.panel{
  max-width: 980px;
  margin: 120px auto 40px;
  background: transparent; border: none; padding: 0;
}
.panel-head{ display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
.tools{ display:flex; gap:8px; align-items:center; }
.muted{ color:#c8d2ff; font-size:13px; }
.block{ display:flex; flex-direction:column; gap:6px; margin:12px 0; }
.block input{ padding:10px 12px; border-radius:10px; border:1px solid #31478f; background:#0a1430; color:#e7ebff; }
.choice-row{ display:flex; align-items:center; gap:10px; margin:8px 0; }
.choice-row input:not([type="radio"]){ flex:1; padding:10px 12px; border-radius:10px; border:1px solid #31478f; background:#0a1430; color:#e7ebff; }
.radio{ display:flex; align-items:center; gap:6px; color:#cdd8ff; }
.msg{ color:#a7f3d0; }

/* 管理テーブル（見やすさのためだけ薄い線） */
.table-wrap{ overflow:auto; }
.tbl{ width:100%; border-collapse:collapse; }
.tbl th, .tbl td{ border:1px solid rgba(42,63,130,.5); padding:8px 10px; text-align:left; background: rgba(4,8,20,.45); }
.tbl thead th{ background: rgba(10,18,45,.75); }
button.danger{ background:#3a0f1a; border:1px solid #7a1f2f; color:#ffd4d8; border-radius:10px; padding:6px 10px; }
/* 縦タブ */
.tabs.vertical{ display:flex; flex-direction:column; gap:8px; width: 88px; }
.tabs.vertical button{
  width:100%; padding:8px 0; border-radius:10px;
  background:#0f1a3a; color:#e7ebff; border:1px solid #2a3f82;
}
.tabs.vertical button.active{ background:#1a2b5e; border-color:#4b66c6; }

/* ===== プレイ面を“画面下”に固定 ===== */
.stage.bottom{
  position: fixed; left:50%; transform: translateX(-50%);
  bottom: 6vh; /* 好みで 4〜10vh に調整 */
  width: min(980px, 92vw);
  margin:0;  /* 上マージン不要 */
}
/* 問題帯・選択肢まわりの間隔を下部用に微調整 */
.prompt{ margin: 12px 0 16px; }
.answers{ margin: 0 0 12px; }
.row{ margin: 10px 0; }


.money-badge{ width: var(--hudWidth); }      /* バッジの横幅を固定 */
.tabs.vertical{
  width: var(--hudWidth);
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.tabs.vertical button{
  display:block;
  width:100%;
  height: 40px;                               /* 高さを統一 */
  line-height: 40px;
  text-align:center;
  box-sizing: border-box;
  border-radius: 10px;
  border:1px solid #2a3f82;
  background: linear-gradient(180deg,#0f1a3a,#0b1530);
  color:#e7ebff;
  letter-spacing:.04em;
  transition: filter .15s ease, transform .02s ease, border-color .2s ease;
}
.tabs.vertical button:hover{
  filter: brightness(1.08);
  border-color:#4b66c6;
}
.tabs.vertical button:active{ transform: translateY(1px); }
.tabs.vertical button.active{
  background: linear-gradient(180deg,#1a2b5e,#132457);
  border-color:#4b66c6;
  box-shadow: 0 0 12px rgba(75,102,198,.25) inset;
}
.tabs.vertical button{
  position: relative;
  /* …上の指定はそのまま… */
}
.tabs.vertical button::before,
.tabs.vertical button::after{
  content:""; position:absolute; top:50%; width:14px; height:14px;
  transform: translateY(-50%) rotate(45deg);
  background: linear-gradient(180deg,#1a2b5e,#0f1a3a);
  border:1px solid #2a3f82; opacity:.8;
}
.tabs.vertical button::before{ left:-7px;  border-right:none; }
.tabs.vertical button::after { right:-7px; border-left:none;  }

/* 小さめ画面の微調整 */
@media (max-width: 640px){
  .answers{ grid-template-columns: 1fr; }
  .money-badge{ width: clamp(240px, 80vw, 440px); }
  .trial{ letter-spacing:.45em; }
}
</style>
