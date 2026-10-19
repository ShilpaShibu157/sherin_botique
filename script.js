document.getElementById("currentYear").textContent = new Date().getFullYear();

const navigation = document.getElementById("mainNavigation");

if (navigation) {
  navigation.querySelectorAll(".nav-link").forEach((link) => {
    link.addEventListener("click", () => {
      if (window.innerWidth < 992) {
        bootstrap.Collapse.getOrCreateInstance(navigation).hide();
      }
    });
  });
}

const chatLauncher = document.getElementById("chatLauncher");
const chatPanel = document.getElementById("chatPanel");

if (chatLauncher && chatPanel) {
  const chatClose = document.getElementById("chatClose");
  const chatMessages = document.getElementById("chatMessages");
  const chatReplyForm = document.getElementById("chatReplyForm");
  const chatReply = document.getElementById("chatReply");
  const leadForm = document.getElementById("leadForm");
  const leadConsent = document.getElementById("leadConsent");
  const answers = {};
  const steps = [
    { key: "name", question: "What should we call you?", maxLength: 100 },
    { key: "phone", question: "What phone number can Sherin reach you on?", maxLength: 32 },
    { key: "interest", question: "What are you looking for? (for example: everyday wear or an occasion outfit)", maxLength: 120 },
    { key: "message", question: "Anything else you’d like Sherin to know? Type a note, or reply SKIP.", maxLength: 1000 },
  ];
  let stepIndex = 0;
  let waitingForConsent = false;

  const setChatOpen = (open) => {
    chatPanel.classList.toggle("is-open", open);
    chatPanel.setAttribute("aria-hidden", String(!open));
    chatLauncher.setAttribute("aria-expanded", String(open));
    if (open) chatReply.focus();
  };

  const addMessage = (text, type) => {
    const message = document.createElement("p");
    message.className = `chat-message ${type === "user" ? "chat-user-message" : "chat-bot-message"}`;
    message.textContent = text;
    chatMessages.append(message);
    chatMessages.scrollTop = chatMessages.scrollHeight;
  };

  const askNextQuestion = () => {
    if (stepIndex < steps.length) {
      const step = steps[stepIndex];
      chatReply.maxLength = step.maxLength;
      chatReply.placeholder = step.key === "phone" ? "Your phone number…" : "Type your reply…";
      addMessage(step.question, "bot");
      return;
    }

    waitingForConsent = true;
    chatReply.maxLength = 20;
    chatReply.placeholder = "Type YES or NO…";
    addMessage("May we save these details so Sherin can follow up? Reply YES to agree or NO to cancel.", "bot");
  };

  chatLauncher.addEventListener("click", () => setChatOpen(!chatPanel.classList.contains("is-open")));
  chatClose.addEventListener("click", () => setChatOpen(false));

  chatReplyForm.addEventListener("submit", (event) => {
    event.preventDefault();
    const value = chatReply.value.trim();
    if (!value) return;

    if (waitingForConsent) {
      const decision = value.toLowerCase();
      if (["yes", "y", "i agree", "agree", "okay", "ok"].includes(decision)) {
        document.getElementById("leadName").value = answers.name;
        document.getElementById("leadPhone").value = answers.phone;
        document.getElementById("leadInterest").value = answers.interest;
        document.getElementById("leadMessage").value = answers.message;
        leadConsent.checked = true;
        leadForm.requestSubmit();
        return;
      }
      if (["no", "n", "no thanks", "cancel"].includes(decision)) {
        addMessage(value, "user");
        Object.keys(answers).forEach((key) => delete answers[key]);
        stepIndex = 0;
        waitingForConsent = false;
        chatMessages.replaceChildren();
        addMessage("No problem. Your details were not sent. If you’d like to try again, what should we call you?", "bot");
        chatReplyForm.hidden = false;
        chatReply.focus();
        return;
      }
      addMessage("Please reply YES to agree or NO to cancel.", "bot");
      chatReply.value = "";
      return;
    }

    const step = steps[stepIndex];
    if (step.key === "phone" && !/^[0-9+().\-\s]{7,32}$/.test(value)) {
      addMessage("Please enter a phone number using digits and common phone symbols.", "bot");
      chatReply.value = "";
      return;
    }
    if (step.key === "name" && value.length > 100) {
      addMessage("Please use a name shorter than 100 characters.", "bot");
      chatReply.value = "";
      return;
    }
    if (step.key === "interest" && value.length > 120) {
      addMessage("Please keep your request under 120 characters.", "bot");
      chatReply.value = "";
      return;
    }

    addMessage(value, "user");
    answers[step.key] = step.key === "message" && ["skip", "no", "none"].includes(value.toLowerCase()) ? "" : value;
    stepIndex += 1;
    chatReply.value = "";
    askNextQuestion();
    chatReply.focus();
  });

  const serverError = document.querySelector(".chat-server-error");
  if (serverError) {
    setChatOpen(true);
    addMessage(serverError.textContent, "bot");
    serverError.remove();
  }
  const serverSuccess = document.querySelector(".chat-server-success");
  if (serverSuccess) {
    setChatOpen(true);
    chatReplyForm.hidden = true;
    addMessage(serverSuccess.textContent, "bot");
    serverSuccess.remove();
  }
}
