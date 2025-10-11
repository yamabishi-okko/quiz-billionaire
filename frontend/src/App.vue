<script setup lang="ts">
import { ref } from 'vue'

/** 型 */
type Question = { id: number; title: string }
type Choice   = { id?: number; body: string; is_correct: boolean }
type RandomResponse = { question: Question; choices: { id:number; body:string }[] }
type CheckResponse  = { correct: boolean; answer?: { id:number; body:string } }

/** APIベースURL（.env.development の VITE_API_BASE を利用） */
const API = import.meta.env.VITE_API_BASE as string

/** タブ切替 */
const tab = ref<'play' | 'create'>('play')

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
</script>

<template>
  <div class="wrap">
    <header>
      <h1>クイズ🪼†-ビリオネア-†</h1>
      <nav class="tabs">
        <button :class="{active: tab==='play'}" @click="tab='play'">プレイ</button>
        <button :class="{active: tab==='create'}" @click="tab='create'">作問</button>
      </nav>
    </header>

    <!-- ============ プレイ ============ -->
    <section v-if="tab==='play'" class="card">
      <div class="row">
        <button @click="fetchRandom" :disabled="loading">
          {{ loading ? '読み込み中…' : 'ランダムで1問' }}
        </button>
      </div>

      <div v-if="data" class="qbox">
        <h2>Q{{ data.question.id }}. {{ data.question.title }}</h2>
        <ul class="choices">
          <li v-for="c in data.choices" :key="c.id">
            <label>
              <input type="radio" name="choice" :value="c.id" v-model="selectedChoiceId">
              {{ c.body }}
            </label>
          </li>
        </ul>

        <div class="row">
          <button @click="checkAnswer" :disabled="selectedChoiceId==null">回答する</button>
        </div>

        <p v-if="result">
          <strong v-if="result.correct" class="ok">正解！</strong>
          <strong v-else class="ng">不正解…</strong>
          <span v-if="result.answer">&nbsp;正解：{{ result.answer.body }}</span>
        </p>
      </div>

      <p v-if="errorMsg" class="error">{{ errorMsg }}</p>
      <p class="hint">先に「ランダムで1問」を押してね。</p>
    </section>

    <!-- ============ 作問 ============ -->
    <section v-else class="card">
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

      <div class="row">
        <button @click="submitCreate" :disabled="creating">登録する</button>
      </div>
      <p v-if="createMsg" class="msg">{{ createMsg }}</p>
    </section>
  </div>
</template>

<style scoped>
/* ------- ダーク基調 ------- */
.wrap { max-width: 760px; margin: 24px auto; padding: 0 16px;
  font-family: system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto; color:#e5e7eb; }
header { display:flex; align-items:center; justify-content:space-between; gap:12px; }
h1 { font-size: 20px; margin:0; font-weight:700; letter-spacing:.02em; }

/* ボタン（見やすい配色） */
button {
  background:#2c2f33; color:#fff; border:1px solid #565b61;
  border-radius:10px; padding:10px 14px; cursor:pointer;
  transition: transform .02s ease, background .2s ease, border-color .2s ease;
}
button:hover { background:#35393e; border-color:#6b7178; }
button:active { transform: translateY(1px); }
button:disabled { background:#5a5f66; color:#e5e7eb; cursor:default; opacity:.7; }

/* タブ */
.tabs { display:flex; gap:8px; }
.tabs button.active { background:#0f172a; border-color:#0f172a; }

/* カード */
.card { background:#1f2124; border:1px solid #3a3f45; border-radius:14px;
  padding:16px; box-shadow:0 6px 24px rgba(0,0,0,.25); }
.row { margin:12px 0; }

/* 出題側 */
.qbox h2 { color:#fff; font-size:18px; margin:12px 0; }
ul.choices { list-style:none; padding:0; }
ul.choices li { margin:8px 0; }
.ok { color:#16a34a; } .ng { color:#ef4444; }
.error { color:#ef4444; } .hint { color:#9ca3af; margin-top: 8px; }

/* 作問側 */
.block { display:flex; flex-direction:column; gap:6px; margin:12px 0; }
.block input { padding:10px 12px; border-radius:10px; border:1px solid #3a3f45; background:#111318; color:#e5e7eb; }
.choices-form { display:flex; flex-direction:column; gap:10px; margin:12px 0; }
.choice-row { display:flex; align-items:center; gap:10px; }
.choice-row input[type="text"], .choice-row input:not([type="radio"]) {
  flex:1; padding:10px 12px; border-radius:10px; border:1px solid #3a3f45; background:#111318; color:#e5e7eb;
}
.radio { display:flex; align-items:center; gap:6px; color:#e5e7eb; }
.msg { color:#a7f3d0; } /* 成功メッセージは薄いグリーン */
</style>
