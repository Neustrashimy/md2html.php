# md2html demo

## Backquotes

> This is a blockquote with two paragraphs. This is first paragraph.
>
> This is second pragraph.Vestibulum enim wisi, viverra nec, fringilla in, laoreet vitae, risus.



## Lists

*   Red
*   Green
*   Blue

## Checkboxes

- [ ] a task list item
- [ ] list syntax required
- [ ] normal **formatting**, @mentions, #1234 refs
- [ ] incomplete
- [x] completed




## Syntax Highlighting

```ruby
require 'redcarpet'
markdown = Redcarpet.new("Hello World!")
puts markdown.to_html
```


## Tables

| Left-Aligned  | Center Aligned  | Right Aligned |
| :------------ | :-------------: | ------------: |
| col 3 is      | some wordy text |         $1600 |
| col 2 is      |    centered     |           $12 |
| zebra stripes |    are neat     |            $1 |



## Links

This is [an example](http://example.com/hogehoge.md "Title") inline link.

Link to http://example.jp/fugafuga.md

[This link](http://example.net/) has no title attribute.

Link to [other page](otherpage.md)

Link to [other domain's md file](http://example.net/fugafuga.md)



## Image

![Alt text](img/かえるぞう.png)

![Alt text @120x90](img/かえるぞう.png)





