# ebooktapbot

**ebooktapbot** is a Telegram chatbot that cut your ebook text content into small sections, and let you read them section by section. You need to click on "Tap" button attached with the section message to read the next section. 

> **ebooktapbot** is no longer exist on Telegram Messenger and the code is updated as it last works.

This code intended for educational purpose only. I learned a lot of new things related to ebook when I developing this chatbot. I also commented the flow (or story) of every code section. Maybe it will help you learn while reading the code. Anyway, check out my story section below!

## How to use (on Telegram)

Basically in this section, I just explain the flow to use the bot and the background process. The first step to do is, uploading the .epub to the bot (there is a part of the code that allow only me to upload and process the .epub file. Do consider to remove it first if you want to allow everybody to upload a new book).

The bot will then extract the text from it and store them into database one by one paragraph and assign ID to each with increment (+1 from previous). The table *line_in_books* refer to the paragraph inside the ebook.

When everything is ready, the bot will return book ID. Click on the book ID to start reading!

## Database
I used PostgreSQL to store all the book info and contents. The attribute and type as follows;

**Table :** books
| Name         | Type    | Notes                        |
|--------------|---------|------------------------------|
| rid          | integer | Auto Increment (PK)          |
| book_id      | text    | Unique ID for book           |
| title        | text    | The title of the book        |
| author       | text    | The author of the book       |
| release_date | text    | The release date of the book |
| language     | text    | The language of the book     |
| publisher    | text    | The publisher                |
| rights       | text    | Copyright                    |
| isbn         | text    | ISBN number (reference)      |
| is_ready     | text    | Is the book ready to read    |

**Table :** line_in_books
| Name    | Type    | Notes                             |
|---------|---------|-----------------------------------|
| book_id | text    | The book ID from books table (FK) |
| line_id | integer | Unique line ID in the book        |
| text    | text    | The text of the paragraph         |

**Table:** user_share_book
| Name          | Type    | Notes                    |
|---------------|---------|--------------------------|
| rid           | integer | Auto Increment (PK)      |
| chat_id       | integer | Telegram User ID         |
| book_id       | text    | Book ID from books table |
| received_date | integer | UNIX Timestamp           |

## Library I used + Reference

1. [OPL's EPUB library](https://sourceforge.net/projects/oplsepublibrary/) — epub reader library
2. [PHP Simple HTML DOM Parser](https://simplehtmldom.sourceforge.io/) — Simple Web Scraping PHP library
3. [Telegram Bot API](https://core.telegram.org/bots/api) — Telegram bot documentation

## Story

One fine day, I decided to read an ebook. So I open up ebook reader, and start reading. A while after, I just getting bored by the way we read an ebook. We read it like the real book; doing the action of right to left for switching pages, reading many paragraphs in a page and doing nothing more than just read. It can be quite boring though (a bit). But, I'm not sure either because of the storyline or me just getting lazy 🤣

So I start to Google on it and I found this famous app called Tap by Wattpad. Basically the idea is, in order for you to read the next paragraph or story, tap on the screen then it will appear. Once finish reading it, tap again for the next paragraph. Sounds cool, isn't? Well, at least for me 😶

Then I start doing my research on ebook. Never know that the ebook format (.epub) is basically a zip file! Rename the extension name into .zip and extract it. You will see various of folders and HTML file. Ok, that is super cool. Whoever did that, legitly clever! I'm serious. Psst. You have no idea how excited I am after I figure out about this hahahaha

Ok continue the story. And then I'm looking for great ebook reader library for PHP and I found one as credited above.

Since the file is using HTML to save the book's content, I also decided to include simple Web Scraping library on PHP. For further processing of course. Basically I'm using this library for removing all unnecessary HTML tags and medias. I only want text from the ebook!

So the focus is h1, h2, h3, h4, h5, h6 and p HTML tags. You can refer more under `epubReader > process_epub_file.php`

And after a long research time and code, everything is ready to use in a day.  Well, by "everything" I mean, the bot did the fundamental which basically the reason it is born.

![Video: Showcase](/assets/video-how-to.gif)