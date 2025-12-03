// aggregate_worker_mysql.js
// Node.js example worker using mysql2/promise
// Install: npm install mysql2
// Configure: set environment variable DATABASE_URL in form mysql://user:pass@host:port/dbname
// Schedule: run daily via cron (e.g., 5 0 * * * node /path/to/aggregate_worker_mysql.js)

const mysql = require('mysql2/promise');
const url = require('url');

if (!process.env.DATABASE_URL) {
  console.error('Please set DATABASE_URL environment variable (mysql://user:pass@host:port/db)');
  process.exit(1);
}

(async () => {
  const dbUrl = new url.URL(process.env.DATABASE_URL);
  const config = {
    host: dbUrl.hostname,
    port: dbUrl.port || 3306,
    user: dbUrl.username,
    password: dbUrl.password,
    database: dbUrl.pathname.replace(/^\/, ''),
    timezone: 'Z',
    decimalNumbers: true,
  };

  const pool = mysql.createPool({ ...config, connectionLimit: 5 });

  async function computeDailyFor(dateStr) {
    const start = dateStr;
    const endDate = new Date(dateStr);
    endDate.setDate(endDate.getDate() + 1);
    const end = endDate.toISOString().slice(0,10);

    const conn = await pool.getConnection();
    try {
      await conn.beginTransaction();

      const q = `
        SELECT user_id,
          MIN(CASE WHEN event_type='IN' THEN occurred_at END) AS first_in,
          MAX(CASE WHEN event_type='OUT' THEN occurred_at END) AS last_out,
          TIMESTAMPDIFF(SECOND,
            MIN(CASE WHEN event_type='IN' THEN occurred_at END),
            MAX(CASE WHEN event_type='OUT' THEN occurred_at END)
          ) AS total_seconds
        FROM attendance_events
        WHERE occurred_at >= ? AND occurred_at < ?
        GROUP BY user_id
      `;
      const [rows] = await conn.query(q, [start, end]);

      for (const row of rows) {
        const { user_id, first_in, last_out, total_seconds } = row;
        const presence = (total_seconds && total_seconds > 0) ? 1 : 0;

        const insertQ = `
          INSERT INTO daily_summary (user_id, day, first_in, last_out, total_work_seconds, presence)
          VALUES (?, ?, ?, ?, ?, ?)
          ON DUPLICATE KEY UPDATE
            first_in = VALUES(first_in),
            last_out = VALUES(last_out),
            total_work_seconds = VALUES(total_work_seconds),
            presence = VALUES(presence),
            updated_at = CURRENT_TIMESTAMP
        `;
        await conn.query(insertQ, [user_id, start, first_in, last_out, total_seconds || 0, presence]);
      }

      await conn.commit();
      console.log('Daily aggregation done for', dateStr, 'rows:', rows.length);
    } catch (err) {
      await conn.rollback();
      console.error('Error in computeDailyFor', err);
      throw err;
    } finally {
      conn.release();
    }
  }

  async function aggregateWeeklyFor(weekStartStr) {
    const ws = new Date(weekStartStr);
    const we = new Date(ws);
    we.setDate(ws.getDate() + 6);
    const weekEndStr = we.toISOString().slice(0,10);

    const q_mysql = `
      INSERT INTO weekly_summary (user_id, week_start, week_end, days_present, total_work_seconds)
      SELECT user_id, ? as week_start, ? as week_end,
        SUM(CASE WHEN presence = 1 THEN 1 ELSE 0 END) AS days_present,
        COALESCE(SUM(total_work_seconds),0) AS total_work_seconds
      FROM daily_summary
      WHERE day >= ? AND day <= ?
      GROUP BY user_id
      ON DUPLICATE KEY UPDATE
        days_present = VALUES(days_present),
        total_work_seconds = VALUES(total_work_seconds),
        updated_at = CURRENT_TIMESTAMP
    `;
    const [res] = await pool.query(q_mysql, [weekStartStr, weekEndStr, weekStartStr, weekEndStr]);
    console.log('Weekly aggregation done for', weekStartStr, 'affectedRows:', res.affectedRows);
  }

  async function aggregateMonthlyFor(monthStartStr) {
    const ms = new Date(monthStartStr);
    const next = new Date(ms);
    next.setMonth(ms.getMonth() + 1);
    const monthEndInclusive = new Date(next);
    monthEndInclusive.setDate(next.getDate() - 1);
    const monthEndStr = monthEndInclusive.toISOString().slice(0,10);

    const q = `
      INSERT INTO monthly_summary (user_id, month, days_present, total_work_seconds)
      SELECT user_id, ? as month,
        SUM(CASE WHEN presence = 1 THEN 1 ELSE 0 END) AS days_present,
        COALESCE(SUM(total_work_seconds),0) AS total_work_seconds
      FROM daily_summary
      WHERE day >= ? AND day <= ?
      GROUP BY user_id
      ON DUPLICATE KEY UPDATE
        days_present = VALUES(days_present),
        total_work_seconds = VALUES(total_work_seconds),
        updated_at = CURRENT_TIMESTAMP
    `;
    const [res] = await pool.query(q, [monthStartStr, monthStartStr, monthEndStr]);
    console.log('Monthly aggregation done for', monthStartStr, 'affectedRows:', res.affectedRows);
  }

  try {
    const today = new Date();
    const yesterday = new Date(today);
    yesterday.setDate(today.getDate() - 1);
    const dayStr = yesterday.toISOString().slice(0,10);

    await computeDailyFor(dayStr);

    if (yesterday.getDay() === 0) {
      const weekStart = new Date(yesterday);
      weekStart.setDate(yesterday.getDate() - 6);
      const weekStartStr = weekStart.toISOString().slice(0,10);
      await aggregateWeeklyFor(weekStartStr);
    }

    const tomorrow = new Date(yesterday);
    tomorrow.setDate(yesterday.getDate() + 1);
    if (tomorrow.getDate() === 1) {
      const monthStart = new Date(yesterday.getFullYear(), yesterday.getMonth(), 1);
      const monthStartStr = monthStart.toISOString().slice(0,10);
      await aggregateMonthlyFor(monthStartStr);
    }

    await pool.end();
    process.exit(0);
  } catch (err) {
    console.error('Worker failed', err);
    await pool.end();
    process.exit(2);
  }
})();
