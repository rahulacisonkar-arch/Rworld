import multiprocessing
import scrapy

scraped_items = []

class RWorldSpider(scrapy.Spider):
    """
    Generic Scrapy Spider for crawling and extracting headings, text, and metadata.
    """
    name = "rworld_spider"

    def __init__(self, start_url=None, *args, **kwargs):
        super(RWorldSpider, self).__init__(*args, **kwargs)
        self.start_urls = [start_url] if start_url else []

    def parse(self, response):
        items = []
        
        quotes = response.css('div.quote')
        if quotes:
            for q in quotes:
                items.append({
                    "text": q.css('span.text::text').get(),
                    "author": q.css('small.author::text').get()
                })
        else:
            for heading in response.css('h1, h2, h3'):
                title = heading.css('::text').get()
                if title:
                    items.append({
                        "type": "heading",
                        "content": title.strip()
                    })
            for paragraph in response.css('p')[:5]:
                text = paragraph.css('::text').get()
                if text and len(text.strip()) > 15:
                    items.append({
                        "type": "paragraph",
                        "content": text.strip()
                    })

        for item in items:
            scraped_items.append(item)
            yield item


def run_spider_process(url: str, results_queue: multiprocessing.Queue):
    from scrapy.crawler import CrawlerRunner
    from twisted.internet import reactor
    
    scraped_items.clear()
    
    runner = CrawlerRunner(settings={
        'USER_AGENT': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'LOG_LEVEL': 'ERROR',
    })
    
    d = runner.crawl(RWorldSpider, start_url=url)
    d.addBoth(lambda _: reactor.stop())
    reactor.run()
    
    results_queue.put(list(scraped_items))


def scrape_url(url: str) -> list:
    """
    Executes the Scrapy spider inside a clean multiprocessing subprocess
    to bypass Twisted's ReactorNotRestartable constraint.
    """
    queue = multiprocessing.Queue()
    p = multiprocessing.Process(target=run_spider_process, args=(url, queue))
    p.start()
    p.join(timeout=15)
    
    if p.is_alive():
        p.terminate()
        p.join()
        return [{"error": "Scrape operation timed out."}]
        
    try:
        return queue.get(block=False)
    except Exception:
        return []
