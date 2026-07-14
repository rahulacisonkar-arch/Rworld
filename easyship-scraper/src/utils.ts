export function parseRobustDate(dateStr: string): Date | null {
  if (!dateStr) return null;
  const cleaned = dateStr.replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim();
  
  // Try standard Date parsing first
  const d = new Date(cleaned);
  if (!isNaN(d.getTime())) {
    // Return midnight local time to avoid timezone offsets causing off-by-one shifts
    return new Date(d.getFullYear(), d.getMonth(), d.getDate());
  }
  
  // Try matching month names manually (e.g. "Jun 9, 2026" or "9 Jun 2026")
  const months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
  const monthRegex = /(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*/i;
  const matchMonth = cleaned.match(monthRegex);
  if (matchMonth) {
    const monthName = matchMonth[1].toLowerCase();
    const monthIndex = months.indexOf(monthName);
    
    // Find day (1 or 2 digits) and year (4 digits)
    const yearMatch = cleaned.match(/\b\d{4}\b/);
    const dayMatch = cleaned.replace(/\b\d{4}\b/, '').match(/\b\d{1,2}\b/);
    
    if (yearMatch && dayMatch && monthIndex !== -1) {
      const year = parseInt(yearMatch[0], 10);
      const day = parseInt(dayMatch[0], 10);
      return new Date(year, monthIndex, day);
    }
  }
  
  // Try matching numeric formats (e.g., DD/MM/YYYY or MM/DD/YYYY)
  const numericMatch = cleaned.match(/(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})/);
  if (numericMatch) {
    const part1 = parseInt(numericMatch[1], 10);
    const part2 = parseInt(numericMatch[2], 10);
    const year = parseInt(numericMatch[3], 10);
    
    if (part1 <= 12 && part2 > 12) {
      // MM/DD/YYYY
      return new Date(year, part1 - 1, part2);
    } else if (part1 > 12 && part2 <= 12) {
      // DD/MM/YYYY
      return new Date(year, part2 - 1, part1);
    } else {
      // Default standard logic: assume MM/DD/YYYY
      return new Date(year, part1 - 1, part2);
    }
  }

  return null;
}
