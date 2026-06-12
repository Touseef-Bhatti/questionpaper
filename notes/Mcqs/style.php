<style>
    .alh-mcq-page { max-width: 1180px; margin: 0 auto; padding: 38px 18px 70px; color: #172033; }
    .alh-mcq-hero { background: linear-gradient(135deg, #0f766e, #2563eb); color: #fff; border-radius: 18px; padding: 42px; box-shadow: 0 22px 55px rgba(37, 99, 235, .18); }
    .alh-mcq-hero h1 { margin: 0 0 12px; font-family: 'Outfit', sans-serif; font-size: clamp(2rem, 4vw, 3.2rem); font-weight: 800; letter-spacing: 0; }
    .alh-mcq-hero p { margin: 0; max-width: 850px; font-size: 1.08rem; line-height: 1.75; color: rgba(255,255,255,.92); }
    .alh-mcq-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px; margin-top: 30px; }
    .alh-mcq-card { display: block; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 22px; text-decoration: none; color: #172033; box-shadow: 0 8px 22px rgba(15, 23, 42, .06); transition: transform .2s, box-shadow .2s, border-color .2s; }
    .alh-mcq-card:hover { transform: translateY(-3px); border-color: #0f766e; box-shadow: 0 16px 32px rgba(15, 23, 42, .1); color: #172033; }
    .alh-mcq-card h2, .alh-mcq-card h3 { margin: 0 0 8px; font-size: 1.2rem; font-weight: 800; color: #0f172a; }
    .alh-mcq-card p { margin: 0; color: #64748b; line-height: 1.55; }
    .alh-mcq-badge { display: inline-flex; align-items: center; gap: 6px; margin-bottom: 14px; padding: 6px 10px; border-radius: 999px; background: #ecfeff; color: #0f766e; font-weight: 800; font-size: .82rem; }
    .alh-mcq-section { margin-top: 34px; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 28px; }
    .alh-mcq-section h2 { margin: 0 0 12px; font-size: 1.75rem; font-weight: 800; color: #0f172a; }
    .alh-mcq-section p, .alh-mcq-section li { color: #475569; line-height: 1.75; }
    .alh-mcq-list { display: grid; gap: 18px; margin-top: 24px; }
    .alh-question { border: 1px solid #dbe4ef; border-radius: 14px; padding: 20px; background: #fbfdff; }
    .alh-question-title { font-weight: 800; color: #0f172a; margin-bottom: 14px; line-height: 1.55; }
    .alh-options { display: grid; gap: 10px; }
    .alh-option { display: flex; gap: 10px; align-items: flex-start; width: 100%; padding: 11px 13px; border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; color: #334155; text-align: left; cursor: pointer; font: inherit; transition: border-color .18s, background .18s, color .18s, transform .18s; }
    .alh-option:hover { border-color: #2563eb; transform: translateY(-1px); }
    .alh-option.is-correct { border-color: #16a34a; background: #f0fdf4; color: #166534; font-weight: 700; }
    .alh-option.is-wrong { border-color: #dc2626; background: #fef2f2; color: #991b1b; font-weight: 700; }
    .alh-option[disabled] { cursor: default; }
    .alh-answer { margin-top: 12px; color: #16a34a; font-weight: 800; }
    .alh-question-actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-top: 14px; }
    .alh-explain-btn { border: 0; border-radius: 10px; padding: 10px 14px; background: #0f766e; color: #fff; font-weight: 800; cursor: pointer; }
    .alh-explanation { display: none; margin-top: 12px; padding: 14px; border-radius: 12px; background: #eff6ff; border: 1px solid #bfdbfe; color: #1e3a8a; line-height: 1.65; }
    .alh-explanation.is-open { display: block; }
    .alh-feedback { font-weight: 800; color: #475569; }
    .alh-feedback.good { color: #15803d; }
    .alh-feedback.bad { color: #b91c1c; }
    .alh-crumbs { margin: 18px 0 0; color: rgba(255,255,255,.9); font-weight: 700; }
    .alh-crumbs a { color: #fff; text-decoration: underline; text-underline-offset: 3px; }
    .alh-empty { padding: 24px; border-radius: 14px; background: #f8fafc; border: 1px dashed #94a3b8; color: #475569; }
    .mcqs-featured-card {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: linear-gradient(135deg, #4f46e5 0%, #0ea5e9 100%);
        border-radius: 20px;
        padding: 30px;
        margin-bottom: 30px;
        box-shadow: 0 15px 35px rgba(79, 70, 229, 0.3);
        text-decoration: none;
        color: white;
        transition: transform 0.3s, box-shadow 0.3s;
        position: relative;
        overflow: hidden;
    }
    .mcqs-featured-card::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
        border-radius: 50%;
    }
    .mcqs-featured-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 40px rgba(79, 70, 229, 0.4);
        color: white;
    }
    .mcqs-featured-content {
        flex: 1;
        position: relative;
        z-index: 2;
    }
    .mcqs-featured-title {
        font-size: 1.8rem;
        font-weight: 800;
        margin-bottom: 10px;
        font-family: 'Outfit', sans-serif;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .mcqs-featured-title i {
        background: rgba(255,255,255,0.2);
        padding: 10px;
        border-radius: 12px;
    }
    .mcqs-featured-desc {
        font-size: 1.05rem;
        opacity: 0.9;
        max-width: 85%;
        line-height: 1.5;
    }
    .mcqs-featured-btn {
        background: white;
        color: #4f46e5;
        padding: 14px 30px;
        border-radius: 50px;
        font-weight: 800;
        font-size: 1.1rem;
        display: flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        transition: transform 0.2s;
        position: relative;
        z-index: 2;
        white-space: nowrap;
    }
    .mcqs-featured-card:hover .mcqs-featured-btn {
        transform: scale(1.05);
    }
    @media (max-width: 768px) {
        .mcqs-featured-card {
            flex-direction: column;
            text-align: center;
            align-items: center;
            gap: 15px;
            padding: 20px;
            margin-bottom: 35px;
            border-radius: 18px;
        }
        .mcqs-featured-title {
            font-size: 1.5rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        .mcqs-featured-desc {
            max-width: 100%;
            font-size: 0.95rem;
        }
        .mcqs-featured-btn {
            width: 100%;
            justify-content: center;
            padding: 12px 20px;
            font-size: 1rem;
        }
    }
    @media (max-width: 640px) { .alh-mcq-hero { padding: 28px 20px; border-radius: 14px; } .alh-mcq-section { padding: 22px 18px; } }
</style>
