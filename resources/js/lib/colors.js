/**
 * Chọn màu chữ (đen/trắng) dễ đọc trên nền hex, vd. nhãn vàng #F2D600 cần chữ tối.
 * Nhận #RGB hoặc #RRGGBB; giá trị lạ -> chữ trắng.
 */
export function readableTextColor(hex) {
    const m = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.exec(String(hex ?? '').trim());
    if (!m) return '#ffffff';

    let h = m[1];
    if (h.length === 3) h = [...h].map((c) => c + c).join('');

    const [r, g, b] = [0, 2, 4].map((i) => parseInt(h.slice(i, i + 2), 16));
    // Độ sáng cảm nhận (công thức YIQ)
    return (r * 299 + g * 587 + b * 114) / 1000 >= 150 ? '#091E42' : '#ffffff';
}
