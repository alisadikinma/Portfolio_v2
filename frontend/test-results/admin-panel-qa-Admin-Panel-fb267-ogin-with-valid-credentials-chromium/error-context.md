# Page snapshot

```yaml
- generic [ref=e5]:
  - generic [ref=e6]:
    - heading "Portfolio V2" [level=1] [ref=e7]
    - paragraph [ref=e8]: Admin Login
  - generic [ref=e9]:
    - generic [ref=e10]:
      - generic [ref=e11]: Email
      - textbox "Email" [ref=e12]:
        - /placeholder: admin@example.com
        - text: admin@alisadikinma.com
    - generic [ref=e13]:
      - generic [ref=e14]: Password
      - generic [ref=e15]:
        - textbox "Password" [ref=e16]:
          - /placeholder: Enter your password
          - text: Passw0rd
        - button [ref=e17]:
          - img [ref=e18]
    - generic [ref=e21]:
      - generic [ref=e22]:
        - checkbox "Remember me" [ref=e23]
        - generic [ref=e24]: Remember me
      - link "Forgot password?" [ref=e25] [cursor=pointer]:
        - /url: "#"
    - paragraph [ref=e27]: Network Error
    - button "Login" [ref=e28]
  - generic [ref=e33]: Or
  - button "Back to Home" [ref=e35]
```