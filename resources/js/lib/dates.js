/**
 * Hiển thị hạn chót của thẻ. Server gửi due_date dạng ISO 8601 (kèm múi giờ).
 *
 *   dueInfo(task) -> null (không có hạn) hoặc { label, title, status }
 *   status: 'done' (đã hoàn thành) | 'overdue' (quá hạn) | 'soon' (còn dưới 24 giờ) | 'normal'
 */
const DAY_MS = 24 * 60 * 60 * 1000;

const pad = (n) => String(n).padStart(2, '0');

export function dueInfo(task, now = new Date()) {
    if (!task?.due_date) return null;

    const due = new Date(task.due_date);
    if (Number.isNaN(due.getTime())) return null;

    // "25/11"; khác năm hiện tại thì thêm năm: "25/11/2027"
    let label = `${pad(due.getDate())}/${pad(due.getMonth() + 1)}`;
    if (due.getFullYear() !== now.getFullYear()) label += `/${due.getFullYear()}`;

    let status = 'normal';
    if (task.completed_at) status = 'done';
    else if (due < now) status = 'overdue';
    else if (due - now <= DAY_MS) status = 'soon';

    const full = `${pad(due.getDate())}/${pad(due.getMonth() + 1)}/${due.getFullYear()} ${pad(due.getHours())}:${pad(due.getMinutes())}`;
    const suffix = { done: ' (đã hoàn thành)', overdue: ' (quá hạn)', soon: ' (sắp đến hạn)', normal: '' }[status];

    return { label, title: `Hạn chót: ${full}${suffix}`, status };
}
