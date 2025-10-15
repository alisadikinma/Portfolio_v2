# Page snapshot

```yaml
- generic [ref=e1]:
  - iframe [ref=e3]:
    - generic [active] [ref=f1e1]:
      - heading "Not Found" [level=1] [ref=f1e2]
      - paragraph [ref=f1e3]: The requested URL was not found on this server.
      - separator [ref=f1e4]
      - generic [ref=f1e5]: Apache/2.4.58 (Win64) OpenSSL/3.1.3 PHP/8.2.12 Server at localhost Port 80
  - main [ref=e6]:
    - generic [ref=e8]:
      - generic [ref=e9]:
        - generic [ref=e10]: Ali Sadikin Portfolio
        - heading "Sign in" [level=1] [ref=e11]
      - generic [ref=e15]:
        - generic [ref=e19]:
          - generic [ref=e22]:
            - generic [ref=e26]:
              - text: Email address
              - superscript [ref=e27]: "*"
            - textbox "Email address*" [ref=e31]: admin@example.com
          - generic [ref=e34]:
            - generic [ref=e38]:
              - text: Password
              - superscript [ref=e39]: "*"
            - generic [ref=e41]:
              - textbox "Password*" [ref=e43]: password
              - button "Show password" [ref=e46] [cursor=pointer]:
                - img [ref=e47]
          - generic [ref=e55]:
            - checkbox "Remember me" [ref=e56]
            - generic [ref=e57]: Remember me
        - button "Sign in" [ref=e63] [cursor=pointer]:
          - generic [ref=e64]: Sign in
  - generic:
    - status
```