import http from 'k6/http';
import { check, group, sleep } from 'k6';
import exec from 'k6/execution';

/**
 * Multi-Room Load Test: 3 Rooms, 100 Users each (300 Total VUs)
 */

export let options = {
  scenarios: {
    room1_flow: {
      executor: 'per-vu-iterations',
      vus: 100,
      iterations: 1,
      maxDuration: '10m',
      startTime: '0s',
    },
    room2_flow: {
      executor: 'per-vu-iterations',
      vus: 100,
      iterations: 1,
      maxDuration: '10m',
      startTime: '10s', // Stagger room starts
    },
    room3_flow: {
      executor: 'per-vu-iterations',
      vus: 100,
      iterations: 1,
      maxDuration: '10m',
      startTime: '20s', // Stagger room starts
    },
  },
  thresholds: {
    http_req_duration: ['p(95)<5000'], // Relaxed for heavy load
    http_req_failed: ['rate<0.1'],
  },
};

// --- CONFIGURATION ---
const HOST_URL = 'https://paper.bhattichemicalsindustry.com.pk';

// Mapping Scenario names to Room Codes
const ROOM_MAPPING = {
  'room1_flow': 'V335RQ',
  'room2_flow': 'KPH5DA', 
  'room3_flow': 'EZDJEH', 
};

function getRandomOption() {
  const opts = ['A', 'B', 'C', 'D'];
  return opts[Math.floor(Math.random() * opts.length)];
}

export default function () {
  const vuId = exec.vu.idInTest;
  const scenarioName = exec.scenario.name;
  const ROOM_CODE = ROOM_MAPPING[scenarioName];

  // STAGGER JOINS: Each user waits 0-5s before joining to avoid session locking
  sleep(Math.random() * 5); 

  const name = `Student_${scenarioName}_V${vuId}`;
  const roll = `R${vuId}`;

  group(`${scenarioName} (Room ${ROOM_CODE})`, function () {
    
    // 1. Join Page
    let joinPage = http.get(`${HOST_URL}/quiz/online_quiz_join.php?room=${ROOM_CODE}`);
    check(joinPage, { 'Join Page 200': (r) => r.status === 200 });

    sleep(1 + Math.random());

    // 2. Perform Join
    let joinRes = http.post(`${HOST_URL}/quiz/online_quiz_join.php`, {
      room_code: ROOM_CODE,
      name: name,
      roll_number: roll,
    }, {
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    });
    
    check(joinRes, { 'Join POST success': (r) => r.status === 200 });

    // 3. Enter Quiz flow
    let isStarted = false;
    let questions = [];
    let participantId = 0;
    let maxRetries = 3;

    for(let retry = 0; retry < maxRetries; retry++) {
      let takePage = http.get(`${HOST_URL}/quiz/online_quiz_take.php?room=${ROOM_CODE}`);
      
      const qMatch = takePage.body.match(/const questions = (\[.*?\]);/);
      if (qMatch) {
        questions = JSON.parse(qMatch[1]);
        const pMatch = takePage.body.match(/const participantId = (\d+);/);
        participantId = pMatch ? parseInt(pMatch[1]) : 0;
        isStarted = true;
        break;
      } else {
        // If not in take page, maybe it's in lobby?
        if (takePage.url.includes('online_quiz_lobby.php')) {
          console.log(`VU ${vuId}: Waiting in Lobby for ${ROOM_CODE}...`);
        } else if (takePage.url.includes('online_quiz_join.php')) {
          console.warn(`VU ${vuId}: Session lost for ${ROOM_CODE}. Bailing.`);
          return;
        }
        sleep(5); // Wait 5s and try entering again
      }
    }

    if (!isStarted) {
      console.error(`VU ${vuId}: FAILED to enter quiz in ${ROOM_CODE} after ${maxRetries} tries.`);
      return;
    }

    let answersList = [];
    let score = 0;

    // 4. Answer loop (Simulate taking the exam)
    for (let i = 0; i < Math.min(questions.length, 50); i++) {
      let q = questions[i];
      let selectedOption = getRandomOption();
      
      let answerPayload = {
        room_code: ROOM_CODE,
        roll_number: roll,
        question_id: q.qrq_id,
        selected_option: selectedOption,
      };

      let ansRes = http.post(`${HOST_URL}/quiz/online_quiz_save_answer.php`, answerPayload, {
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      });

      check(ansRes, { 'Answered Successfully': (r) => r.status === 200 });

      answersList.push({
        question_id: q.qrq_id,
        selected: selectedOption,
      });

      sleep(Math.random() * 2 + 1);
    }

    // 5. Final Submission (simulated)
    let submitPayload = JSON.stringify({
      participant_id: participantId,
      room_code: ROOM_CODE,
      score: score,
      total: questions.length,
      answers: answersList
    });

    let submitRes = http.post(`${HOST_URL}/quiz/online_quiz_submit.php`, submitPayload, {
      headers: { 'Content-Type': 'application/json' },
    });

    check(submitRes, { 'Quiz Finalized': (r) => r.status === 200 });
  });
}